<?php

namespace App\Services\Mail;

use App\Models\Attachment;
use App\Models\InboundEmail;
use App\Models\Ticket;
use App\Models\TicketMessageId;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Report klientovi po dokončení práce: odpověď na původní e-mail (např. RITM od Zásilkovny)
 * ve stejném vlákně – popis práce, km a fotky z návštěvy jako přílohy.
 */
class ClientReportMailer
{
    /** Původní e-mail, na který se odpovídá */
    public function sourceEmail(Ticket $ticket): ?InboundEmail
    {
        return InboundEmail::with('mailbox')->where('ticket_id', $ticket->id)
            ->whereIn('status', ['ticket', 'review'])->oldest('id')->first();
    }

    /** Předvyplněný návrh – dispečer ho může před odesláním upravit */
    public function draft(Ticket $ticket): array
    {
        $email = $this->sourceEmail($ticket);
        if (! $email) {
            throw new RuntimeException('Zakázka nevznikla z e-mailu – není na co odpovědět.');
        }

        $ticket->loadMissing(['location', 'visits.technician', 'visits.parts', 'visits.attachments']);
        $visits = $ticket->visits->whereIn('status', ['submitted', 'approved'])->sortBy('id')->values();
        if ($visits->isEmpty()) {
            $visits = $ticket->visits->sortBy('id')->values();
        }

        $test = ['ok' => 'v pořádku', 'failed' => 'nevyhověl', 'na' => 'neprováděl se'];
        $lines = ['Dobrý den,', ''];
        $lines[] = trim(($ticket->external_ref ? $ticket->external_ref.' – ' : '').($ticket->location ? $ticket->location->code.' '.$ticket->location->name : $ticket->title));
        $lines[] = 'Naše číslo zakázky: '.$ticket->number;
        $lines[] = '';

        $km = 0.0;
        foreach ($visits as $v) {
            $date = ($v->arrived_at ?? $v->created_at)?->timezone('Europe/Prague')->format('d.m.Y H:i');
            $lines[] = 'Technik: '.$v->technician?->name.($date ? ', '.$date : '');
            if ($v->work_description) {
                $lines[] = 'Provedené práce: '.$v->work_description;
            }
            if ($v->repair_description) {
                $lines[] = 'Příčina / oprava: '.$v->repair_description;
            }
            if ($v->test_result) {
                $lines[] = 'Test: '.($test[$v->test_result->value] ?? $v->test_result->value).($v->test_note ? ' – '.$v->test_note : '');
            }
            foreach ($v->parts as $p) {
                $sn = array_filter([$p->serial_removed ? 'S/N demont. '.$p->serial_removed : null, $p->serial_installed ? 'S/N mont. '.$p->serial_installed : null]);
                $lines[] = 'Díl: '.$p->name.' '.rtrim(rtrim(number_format($p->quantity, 2, ',', ''), '0'), ',').' ks'.($sn ? ' ('.implode(', ', $sn).')' : '');
            }
            $km += (float) $v->km;
            $lines[] = '';
        }
        $lines[] = 'Ujeto: '.rtrim(rtrim(number_format($km, 1, ',', ''), '0'), ',').' km';
        $lines[] = '';
        $lines[] = 'Fotodokumentace v příloze.';
        $lines[] = '';
        $lines[] = 'S pozdravem';
        $lines[] = 'PAP Service';

        $photos = $visits->flatMap(fn ($v) => $v->attachments->where('kind', 'photo'))->values();

        return [
            'to' => $email->from_email,
            'subject' => $this->subject($email),
            'body' => implode("\n", $lines),
            'photos' => $photos->map(fn (Attachment $a) => ['uuid' => $a->uuid, 'name' => $a->original_name])->all(),
            'can_send' => $email->mailbox->canSend(),
            'sent_at' => $ticket->client_report_sent_at?->toIso8601String(),
        ];
    }

    /** @param list<string> $photoUuids */
    public function send(Ticket $ticket, string $body, array $photoUuids, User $by): void
    {
        $email = $this->sourceEmail($ticket);
        if (! $email || ! $email->from_email) {
            throw new RuntimeException('Zakázka nevznikla z e-mailu – není na co odpovědět.');
        }
        $mb = $email->mailbox;
        if (! $mb->canSend()) {
            throw new RuntimeException('Schránka „'.$mb->name.'“ nemá nastavené odesílání (SMTP).');
        }

        // jen fotky z této zakázky (z návštěv nebo přímo u zakázky)
        $visitIds = $ticket->visits()->pluck('id');
        $files = Attachment::whereIn('uuid', $photoUuids)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('attachable_type', 'visit')->whereIn('attachable_id', $visitIds))
                ->orWhere(fn ($w) => $w->where('attachable_type', 'ticket')->where('attachable_id', $ticket->id)))
            ->get();

        $domain = Str::after((string) ($mb->from_address ?: $mb->smtp_username), '@') ?: 'servicepap.com';
        $ourId = 'pap-report-'.$ticket->id.'-'.Str::lower(Str::random(12)).'@'.$domain;
        $strip = fn ($id) => trim($id, '<> ');
        $refs = array_values(array_unique(array_filter([...$email->referencedIds(), $email->message_id])));

        $mailer = Mail::build([
            'transport' => config('pap.confirmation_transport', 'smtp'),
            'scheme' => $mb->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $mb->smtp_host,
            'port' => $mb->smtp_port ?: ($mb->smtp_encryption === 'ssl' ? 465 : 587),
            'username' => $mb->smtp_username,
            'password' => $mb->smtp_password,
            'timeout' => 60,
        ]);

        $mailer->raw($body, function (Message $m) use ($email, $mb, $files, $ourId, $refs, $strip) {
            $m->to($email->from_email)
                ->from($mb->from_address ?: $mb->smtp_username, $mb->from_name ?: 'PAP Service')
                ->subject($this->subject($email));
            foreach ($files as $i => $f) {
                $m->attachData(Storage::disk($f->disk)->get($f->path), $f->original_name ?: ('foto-'.($i + 1).'.jpg'), ['mime' => $f->mime ?: 'image/jpeg']);
            }
            $h = $m->getSymfonyMessage()->getHeaders();
            $h->remove('Message-ID');
            $h->addIdHeader('Message-ID', $ourId);
            $h->addIdHeader('In-Reply-To', $strip($email->message_id));
            $h->addIdHeader('References', array_map($strip, $refs));
        });

        DB::transaction(function () use ($ticket, $by, $ourId, $files) {
            TicketMessageId::remember($ticket->id, '<'.$ourId.'>');
            $ticket->update(['client_report_sent_at' => now(), 'client_report_by' => $by->id]);
            $ticket->history()->create([
                'from_status' => $ticket->status->value,
                'to_status' => $ticket->status->value,
                'user_id' => $by->id,
                'note' => '📤 Report odeslán klientovi ('.$files->count().' fotek)',
            ]);
        });
    }

    private function subject(InboundEmail $email): string
    {
        $s = (string) ($email->subject ?: 'Servis');

        return preg_match('/^(re|odp|aw)\s*:/i', $s) ? $s : 'Re: '.$s;
    }
}
