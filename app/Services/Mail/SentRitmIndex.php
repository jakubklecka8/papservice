<?php

namespace App\Services\Mail;

use App\Enums\TicketStatus;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * RITM, na které už ze schránky odešla odpověď (složka Odeslané) = vyřízené jinde
 * (typicky ve staré aplikaci, dokud běží souběžně). Takové se nezakládají,
 * a netknuté zakázky s takovým RITM se zruší.
 */
class SentRitmIndex
{
    public function __construct(private readonly MailboxReader $reader) {}

    public function enabled(Mailbox $mb): bool
    {
        return $mb->parser === 'zasilkovna_ritm' && filled($mb->sent_folder);
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

        try {
            $since = ($mb->import_since ?? now())->copy()->subDays(30);
            $set = [];
            foreach ($this->reader->subjectsSince($mb, $mb->sent_folder, $since) as $subject) {
                if (preg_match_all('/RITM\d+/i', $subject, $m)) {
                    foreach ($m[0] as $r) {
                        $set[strtoupper($r)] = true;
                    }
                }
            }
            Cache::put($this->key($mb), $set, 600);

            return $set;
        } catch (Throwable $e) {
            report($e);

            return Cache::get($this->key($mb)) ?? [];
        }
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
                $t->changeStatus(TicketStatus::Cancelled, null, 'Vyřízeno mimo PAP Service – odpověď na '.$t->external_ref.' je v Odeslaných.');
                $n++;
            });

        return $n;
    }

    private function key(Mailbox $mb): string
    {
        return 'mb:'.$mb->id.':sent-ritm';
    }
}
