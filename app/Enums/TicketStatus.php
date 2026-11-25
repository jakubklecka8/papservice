<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';                 // nový, čeká na kontrolu dispečera
    case Assigned = 'assigned';       // přidělený technikovi
    case InProgress = 'in_progress';  // technik na místě / pracuje
    case Waiting = 'waiting';         // čeká (díl, přístup, zákazník)
    case Completed = 'completed';     // technik dokončil, čeká na schválení protokolu
    case Closed = 'closed';           // protokol schválen
    case Cancelled = 'cancelled';

    public function label(string $locale = 'cs'): string
    {
        $labels = [
            'cs' => ['new' => 'Nový', 'assigned' => 'Přidělený', 'in_progress' => 'Probíhá', 'waiting' => 'Čeká',
                'completed' => 'Dokončený', 'closed' => 'Uzavřený', 'cancelled' => 'Zrušený'],
            'pl' => ['new' => 'Nowe', 'assigned' => 'Przydzielone', 'in_progress' => 'W toku', 'waiting' => 'Oczekuje',
                'completed' => 'Zakończone', 'closed' => 'Zamknięte', 'cancelled' => 'Anulowane'],
        ];

        return $labels[$locale][$this->value] ?? $labels['cs'][$this->value];
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Cancelled], true);
    }
}
