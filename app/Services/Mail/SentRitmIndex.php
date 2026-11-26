<?php

namespace App\Services\Mail;

use App\Enums\TicketStatus;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RITM vyřízená jinde – dokud běží souběžně stará aplikace:
 *  - odpověď ve složce Odeslané, nebo
 *  - ve staré DB (service_calls) ve stavu „dokonceno“.
 * Taková se nezakládají a netknuté zakázky s takovým RITM se zruší.
 */
class SentRitmIndex
{
    public function __construct(private readonly MailboxReader $reader) {}

    public function enabled(Mailbox $mb): bool
    {
        return $mb->parser === 'zasilkovna_ritm' && (filled($mb->sent_folder) || $this->legacyEnabled());
    }

    public function legacyEnabled(): bool
    {
        return filled(config('database.connections.legacy.database'));
    }

    /** @return list<string> RITM dokončená ve staré aplikaci */
    private function legacyDone(\DateTimeInterface $since): array
    {
        return DB::connection('legacy')->table('service_calls')
            ->where('status', 'dokonceno')
            ->where('created_at', '>=', $since)
            ->whereNotNull('ritm_number')
            ->pluck('ritm_number')->all();
    }

    /** @return array<string, true> RITM → true */
    public function answered(Mailbox $mb): array
    {
        if (! $this->enabled($mb)) {
            return [];
        }

        return Cache::get($this->key($mb)) ?? $this->refresh($mb);
    }

    /** @return array<string, true> */
    public function refresh(Mailbox $mb): array
    {
        if (! $this->enabled($mb)) {
            return [];
        }

        $since = ($mb->import_since ?? now())->copy()->subDays(30);
        $set = [];
        $failed = false;

        if (filled($mb->sent_folder)) {
            try {
                foreach ($this->reader->subjectsSince($mb, $mb->sent_folder, $since) as $subject) {
                    if (preg_match_all('/RITM\d+/i', $subject, $m)) {
                        foreach ($m[0] as $r) {
                            $set[strtoupper($r)] = true;
                        }
                    }
                }
            } catch (Throwable $e) {
                report($e);
                $failed = true;
            }
        }

        if ($this->legacyEnabled()) {
            try {
                foreach ($this->legacyDone($since) as $r) {
                    $set[strtoupper(trim($r))] = true;
                }
            } catch (Throwable $e) {
                report($e);
                $failed = true;
            }
        }

        if ($failed && ! $set) {
            return Cache::get($this->key($mb)) ?? [];
        }
        Cache::put($this->key($mb), $set, 600);

        return $set;
    }

    /** Netknuté zakázky (nové, bez technika, bez návštěvy), na jejichž RITM už je odpověď → zrušit */
    public function cancelAnsweredElsewhere(Mailbox $mb, array $answered): int
    {
        if (! $answered) {
            return 0;
        }
        $n = 0;
        Ticket::where('project_id', $mb->project_id)
            ->whereIn('external_ref', array_keys($answered))
            ->where('status', TicketStatus::New->value)
            ->whereNull('assigned_technician_id')
            ->whereDoesntHave('visits')
            ->each(function (Ticket $t) use (&$n) {
                $t->changeStatus(TicketStatus::Cancelled, null, 'Vyřízeno mimo PAP Service – '.$t->external_ref.' je hotové ve staré aplikaci / odpovězené.');
                $n++;
            });

        return $n;
    }

    private function key(Mailbox $mb): string
    {
        return 'mb:'.$mb->id.':sent-ritm';
    }
}
