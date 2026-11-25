<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Ticket;
use App\Models\User;

/** Texty push notifikací pro techniky (v jazyce technika) */
class TechnicianNotifier
{
    public function assigned(Ticket $ticket, User $technician): void
    {
        $ticket->loadMissing('location');
        $pl = $technician->locale === 'pl';
        $prio = in_array($ticket->priority, ['high', 'urgent'], true) ? ($pl ? '❗ ' : '❗ ') : '';

        $this->send($technician, $ticket, [
            'title' => $prio.($pl ? 'Nowe zlecenie ' : 'Nová zakázka ').$ticket->number,
            'body' => $this->line($ticket),
        ]);
    }

    public function returned(Ticket $ticket, User $technician, string $note): void
    {
        $pl = $technician->locale === 'pl';
        $this->send($technician, $ticket, [
            'title' => ($pl ? 'Wizyta zwrócona ' : 'Vrácená návštěva ').$ticket->number,
            'body' => mb_substr($note, 0, 180),
        ]);
    }

    public function cancelled(Ticket $ticket, User $technician): void
    {
        $pl = $technician->locale === 'pl';
        $this->send($technician, $ticket, [
            'title' => ($pl ? 'Zlecenie anulowane ' : 'Zakázka zrušena ').$ticket->number,
            'body' => $this->line($ticket),
        ]);
    }

    private function line(Ticket $ticket): string
    {
        $loc = $ticket->location;
        $where = $loc ? trim($loc->code.' · '.($loc->city ?: $loc->name)) : '';

        return mb_substr(trim($ticket->title.($where ? ' – '.$where : '')), 0, 180);
    }

    private function send(User $user, Ticket $ticket, array $payload): void
    {
        SendPushNotification::dispatch($user->id, $payload + [
            'url' => '/technik/#/t/'.$ticket->id,
            'tag' => 'ticket-'.$ticket->id,
        ]);
    }
}
