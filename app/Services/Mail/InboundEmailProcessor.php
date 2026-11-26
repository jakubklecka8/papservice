<?php

namespace App\Services\Mail;

use App\Enums\TicketStatus;
use App\Jobs\SendTicketConfirmation;
use App\Models\Attachment;
use App\Models\EndCustomer;
use App\Models\InboundEmail;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMessageId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Zpracování uložené zprávy:
 *  - odpověď ve vlákně existující zakázky → připojí text a přílohy k ní (3.4)
 *  - nový požadavek → AI + dohledání provozovny → zakázka; nejasná provozovna = k prověření dispečerem
 *  - potvrzení s číslem se odešle až po úspěšném uložení, právě jednou (3.3)
 */
class InboundEmailProcessor
{
    public function __construct(
        private readonly EmailClassifier $classifier,
        private readonly LocationMatcher $matcher,
        private readonly RitmParser $ritm,
    ) {}

    public function process(InboundEmail $email): void
    {
        if ($email->status !== 'new') {
            return;
        }
        $email->loadMissing('mailbox');

        try {
            if ($ticket = $this->findThread($email)) {
                $this->append($email, $ticket);

                return;
            }

            if ($email->mailbox->parser === 'zasilkovna_ritm') {
                $ticket = $this->processRitm($email);
                if ($ticket === null) {
                    return; // ignorováno nebo připojeno k existující zakázce
                }
            } else {
                $ai = $this->classifier->classify($email);

                if (! $ai['is_request'] && ! $email->mailbox->dedicated) {
                    $email->update(['status' => 'ignored', 'ai_result' => $ai, 'processed_at' => now()]);

                    return;
                }

                $match = $this->matcher->match(
                    $email->mailbox->project_id,
                    $email->subject."\n".$email->body_text,
                    $ai['location_hints'] ?? [],
                );
                $ai['candidates'] = $match['candidates'];

                $ticket = $this->createTicket($email, $ai, $match['location']);
            }
        } catch (Throwable $e) {
            report($e);
            $email->update(['status' => 'error', 'error' => Str::limit($e->getMessage(), 1000), 'processed_at' => now()]);

            return; // při chybě uložení se potvrzení neodesílá
        }

        if ($email->mailbox->auto_reply && $email->mailbox->canSend() && $email->from_email) {
            SendTicketConfirmation::dispatch($email->id);
        }
    }

    /**
     * Notifikace Zásilkovny s RITM: bez AI, podle pevného formátu.
     * Bez RITM → ignorovat. Stejné RITM → připojit k existující zakázce.
     * Neznámý box → založit provozovnu z údajů v e-mailu.
     */
    private function processRitm(InboundEmail $email): ?Ticket
    {
        $projectId = $email->mailbox->project_id;
        $ref = $this->ritm->ritm($email->subject);
        if ($ref === null) {
            $email->update(['status' => 'ignored', 'processed_at' => now(), 'ai_result' => ['engine' => 'ritm', 'reason' => 'Předmět neobsahuje RITM.']]);

            return null;
        }

        if ($existing = Ticket::where('project_id', $projectId)->where('external_ref', $ref)->first()) {
            $this->append($email, $existing);

            return null;
        }

        $data = $this->ritm->parse((string) $email->body_text);
        if ($data === null) {
            throw new \RuntimeException("E-mail {$ref} nejde přečíst (chybí „Service type“ nebo „Locker: … ID: …“). Zpracuj ručně.");
        }

        return DB::transaction(function () use ($email, $projectId, $ref, $data) {
            $location = $this->ritmLocation($projectId, $data, $ref);
            $types = $email->mailbox->project->service_types ?? ['servis'];
            $type = in_array('profilaxe', $types, true) && preg_match('/profyla|profila|prevent|maintenance|údržb/iu', $data['service_type']) ? 'profilaxe' : ($types[0] ?? 'servis');

            $ticket = Ticket::create([
                'project_id' => $projectId,
                'end_customer_id' => $location->end_customer_id,
                'location_id' => $location->id,
                'external_ref' => $ref,
                'service_type' => $type,
                'priority' => $data['priority'],
                'status' => TicketStatus::New,
                'source' => 'email',
                'needs_review' => false,
                'title' => Str::limit($data['service_type'].' – '.$data['locker_code'], 250, ''),
                'description' => trim($data['description']."\n\n".($data['location'] ? 'Location: '.$data['location'] : '').($data['priority_raw'] ? "\nPriority: ".$data['priority_raw'] : '')),
                'reporter_name' => $email->from_name,
                'reporter_email' => $email->from_email,
            ]);
            $ticket->history()->create(['to_status' => TicketStatus::New->value, 'note' => '📧 '.$ref.' z e-mailu '.$email->from_email]);
            $this->attachFiles($email, $ticket);
            TicketMessageId::remember($ticket->id, $email->message_id);
            $email->update(['status' => 'ticket', 'ticket_id' => $ticket->id, 'processed_at' => now(),
                'ai_result' => ['engine' => 'ritm', 'reason' => $ref.' · '.$data['locker_code'].' (ID '.$data['locker_id'].')', 'language' => 'cs']]);

            return $ticket;
        });
    }

    private function ritmLocation(int $projectId, array $d, string $ref): Location
    {
        $loc = Location::where('project_id', $projectId)
            ->where(fn ($q) => $q->where('external_id', $d['locker_id'])->orWhereRaw('UPPER(code) = ?', [$d['locker_code']]))
            ->first();

        if ($loc) {
            $fill = [];
            if (! $loc->external_id) {
                $fill['external_id'] = $d['locker_id'];
            }
            if ($loc->lat === null && $d['gps_lat'] !== null) {
                $fill += ['lat' => $d['gps_lat'], 'lng' => $d['gps_lon']];
            }
            if ($fill) {
                $loc->update($fill);
            }

            return $loc;
        }

        $addr = $this->ritm->splitAddress($d['location']);

        return Location::create([
            'project_id' => $projectId,
            'end_customer_id' => EndCustomer::where('project_id', $projectId)->value('id'),
            'code' => $d['locker_code'],
            'external_id' => $d['locker_id'],
            'name' => Str::limit($d['location'] ?: 'Z-BOX '.$d['locker_code'], 250, ''),
            'street' => $addr['street'],
            'city' => $addr['city'],
            'zip' => $addr['zip'],
            'country' => substr($d['locker_code'], 0, 2) === 'PL' ? 'PL' : (substr($d['locker_code'], 0, 2) === 'SK' ? 'SK' : 'CZ'),
            'lat' => $d['gps_lat'],
            'lng' => $d['gps_lon'],
            'note' => 'Založeno automaticky z e-mailu '.$ref,
        ]);
    }

    /** Zakázka, na kterou zpráva odpovídá (podle Message-ID ve vlákně, nebo čísla v předmětu) */
    public function findThread(InboundEmail $email): ?Ticket
    {
        $ticketId = TicketMessageId::ticketFor($email->referencedIds());
        if ($ticketId) {
            return Ticket::find($ticketId);
        }

        if ($email->subject && preg_match('/PAP-\d{4}-\d{6}/', $email->subject, $m)) {
            return Ticket::where('number', $m[0])->where('project_id', $email->mailbox->project_id)->first();
        }

        return null;
    }

    private function append(InboundEmail $email, Ticket $ticket): void
    {
        DB::transaction(function () use ($email, $ticket) {
            $this->attachFiles($email, $ticket);
            $ticket->history()->create([
                'from_status' => $ticket->status->value,
                'to_status' => $ticket->status->value,
                'note' => '📧 '.($email->from_name ?: $email->from_email).': '.Str::limit($this->stripQuoted((string) $email->body_text), 900),
            ]);
            $ticket->touch();
            TicketMessageId::remember($ticket->id, $email->message_id);
            $email->update(['status' => 'appended', 'ticket_id' => $ticket->id, 'processed_at' => now()]);
        });
    }

    private function createTicket(InboundEmail $email, array $ai, $location): Ticket
    {
        return DB::transaction(function () use ($email, $ai, $location) {
            $projectId = $email->mailbox->project_id;
            $endCustomerId = $location?->end_customer_id;
            if (! $endCustomerId && ! empty($ai['end_customer_code'])) {
                $endCustomerId = EndCustomer::where('project_id', $projectId)->where('code', $ai['end_customer_code'])->value('id');
            }

            $description = trim(($ai['summary'] ? $ai['summary']."\n\n— — —\n" : '').Str::limit($this->stripQuoted((string) $email->body_text), 9000));

            $ticket = Ticket::create([
                'project_id' => $projectId,
                'end_customer_id' => $endCustomerId,
                'location_id' => $location?->id,
                'service_type' => $ai['service_type'],
                'priority' => $ai['priority'],
                'status' => TicketStatus::New,
                'source' => 'email',
                'needs_review' => $location === null,
                'title' => $ai['title'] ?: 'Požadavek z e-mailu',
                'description' => $description ?: null,
                'reporter_name' => $email->from_name,
                'reporter_email' => $email->from_email,
            ]);
            $ticket->history()->create([
                'to_status' => TicketStatus::New->value,
                'note' => '📧 Z e-mailu '.$email->from_email.($location ? '' : ' – provozovnu je potřeba ověřit'),
            ]);
            $this->attachFiles($email, $ticket);
            TicketMessageId::remember($ticket->id, $email->message_id);

            $email->update([
                'status' => $location ? 'ticket' : 'review',
                'ticket_id' => $ticket->id,
                'ai_result' => $ai,
                'processed_at' => now(),
            ]);

            return $ticket;
        });
    }

    private function attachFiles(InboundEmail $email, Ticket $ticket): void
    {
        foreach ($email->attachments ?? [] as $i => $f) {
            Attachment::firstOrCreate(
                ['uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'pap-email:'.$email->message_hash.'#'.$i)->toString()],
                [
                    'attachable_type' => 'ticket',
                    'attachable_id' => $ticket->id,
                    'kind' => str_starts_with((string) $f['mime'], 'image/') ? 'photo' : 'document',
                    'disk' => 'local',
                    'path' => $f['path'],
                    'original_name' => $f['name'],
                    'mime' => $f['mime'],
                    'size' => $f['size'],
                ],
            );
        }
    }

    /** Odřízne citovanou předchozí korespondenci („> …“, „Dne … napsal:“, „From: …“) */
    private function stripQuoted(string $text): string
    {
        $lines = preg_split('/\R/', $text);
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\s*>|-{2,}\s*Original|-----Původní|-----Oryginalna|Dne .+ napsal|W dniu .+ napisał|On .+ wrote:|From:\s|Od:\s)/iu', $line)) {
                break;
            }
            $out[] = $line;
        }

        return trim(implode("\n", $out)) ?: trim($text);
    }
}
