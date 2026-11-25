<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active === false ? false : null;
    }

    /**
     * Stažení souboru. Kontroluje se vždy přes zakázku – přímý odkaz bez oprávnění
     * nic nevrátí (3.2). Zákazník vidí jen fotky označené pro zákazníka nebo své vlastní.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket();
        if ($ticket === null || ! $ticket->isVisibleTo($user)) {
            return false;
        }

        if ($user->isCustomerOnly()) {
            return $attachment->customer_visible || $attachment->uploaded_by === $user->id;
        }

        return true;
    }

    public function update(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket();

        return $ticket !== null && $user->can('manage', $ticket);
    }
}
