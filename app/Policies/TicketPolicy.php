<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active === false ? false : null;
    }

    public function viewAny(User $user): bool
    {
        return true; // seznam je vždy filtrovaný přes Ticket::visibleTo()
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->isVisibleTo($user);
    }

    /** Založení: admin, dispečer (projekt se ověří v kontroleru), zákazník (jen svá provozovna) */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dispecer, Role::Zakaznik);
    }

    /** Správa zakázky: úprava, přidělení, stav, protokol */
    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || in_array($ticket->project_id, $user->dispatcherProjectIds(), true);
    }

    /** Práce technika na přidělené zakázce */
    public function work(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Technik)
            && $ticket->assigned_technician_id === $user->id
            && $ticket->status->isOpen();
    }

    /** Zákazník provozovny (smí přikládat fotky ke svému požadavku) */
    public function contribute(User $user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket)
            || $this->work($user, $ticket)
            || ($ticket->location_id !== null && in_array($ticket->location_id, $user->customerLocationIds(), true));
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
