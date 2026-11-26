<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Přidělení zakázky technikovi (z detailu, z plánovacího kalendáře i z harmonogramu) */
class TicketAssigner
{
    public function assign(Ticket $ticket, User $technician, ?User $by, ?string $note = null): void
    {
        abort_unless($technician->is_active && $technician->hasRole(Role::Technik), 422, 'Vybraný uživatel není aktivní technik.');
        abort_unless($ticket->status->isOpen(), 422, 'Uzavřenou zakázku nelze přidělit.');

        $previous = $ticket->assigned_technician_id;
        if ($previous === $technician->id) {
            return;
        }

        DB::transaction(function () use ($ticket, $technician, $by, $note) {
            $ticket->fill(['assigned_technician_id' => $technician->id, 'assigned_by' => $by?->id, 'assigned_at' => now()])->save();

            if (in_array($ticket->status, [TicketStatus::New, TicketStatus::Assigned], true)) {
                $ticket->changeStatus(TicketStatus::Assigned, $by, $note ?? 'Přiděleno: '.$technician->name);
            } else {
                $ticket->history()->create([
                    'from_status' => $ticket->status->value,
                    'to_status' => $ticket->status->value,
                    'user_id' => $by?->id,
                    'note' => 'Přeřazeno: '.$technician->name,
                ]);
            }
        });

        app(TechnicianNotifier::class)->assigned($ticket, $technician);
    }
}
