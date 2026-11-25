<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TestResult;
use App\Enums\TicketStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VisitResource;
use App\Models\Ticket;
use App\Models\Visit;
use App\Services\TechnicianNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Záznam návštěvy technika. Ukládá se idempotentně podle UUID z telefonu:
 * PUT stejného UUID vícekrát (offline fronta, opakované odeslání) = jeden záznam.
 */
class VisitController extends Controller
{
    public function upsert(Request $request, Ticket $ticket, string $uuid): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('work', $ticket) || $user->isAdmin(), 403);
        abort_unless(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1, 422, 'Neplatné UUID.');

        $data = $request->validate([
            'arrived_at' => ['nullable', 'date'],
            'departed_at' => ['nullable', 'date', 'after_or_equal:arrived_at'],
            'minutes_on_site' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'work_description' => ['nullable', 'string', 'max:10000'],
            'repair_description' => ['nullable', 'string', 'max:10000'],
            'test_result' => ['nullable', Rule::enum(TestResult::class)],
            'test_note' => ['nullable', 'string', 'max:2000'],
            'instruction_ref' => ['nullable', 'string', 'max:255'],
            'client_updated_at' => ['nullable', 'date'],
            'parts' => ['sometimes', 'array', 'max:100'],
            'parts.*.uuid' => ['required', 'uuid'],
            'parts.*.name' => ['required', 'string', 'max:255'],
            'parts.*.part_number' => ['nullable', 'string', 'max:100'],
            'parts.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'parts.*.serial_removed' => ['nullable', 'string', 'max:100'],
            'parts.*.serial_installed' => ['nullable', 'string', 'max:100'],
            'parts.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        // Datum s časovou zónou z telefonu převést na UTC (jinak by se offset ztratil)
        foreach (['arrived_at', 'departed_at', 'client_updated_at'] as $f) {
            if (! empty($data[$f])) {
                $data[$f] = Carbon::parse($data[$f])->utc();
            }
        }

        $visit = Visit::where('uuid', $uuid)->first();
        $created = $visit === null;

        if ($visit) {
            abort_unless($visit->ticket_id === $ticket->id, 409, 'UUID patří jiné zakázce.');
            abort_unless($visit->technician_id === $user->id || $user->isAdmin(), 403);

            if (! $visit->isEditable()) {
                return $this->current($visit, 409, 'Návštěva už byla odeslána ke schválení.');
            }

            // Starší verze z telefonu nepřepíše novější data
            if (! empty($data['client_updated_at']) && $visit->client_updated_at
                && $data['client_updated_at']->lt($visit->client_updated_at)) {
                return $this->current($visit, 200, 'Na serveru je novější verze.');
            }
        }

        DB::transaction(function () use (&$visit, $ticket, $user, $uuid, $data) {
            $fields = collect($data)->except('parts')->all();
            if (empty($fields['minutes_on_site']) && ! empty($fields['arrived_at']) && ! empty($fields['departed_at'])) {
                $fields['minutes_on_site'] = (int) $fields['arrived_at']->diffInMinutes($fields['departed_at']);
            }

            if ($visit === null) {
                $visit = Visit::create($fields + [
                    'uuid' => strtolower($uuid),
                    'ticket_id' => $ticket->id,
                    'technician_id' => $ticket->assigned_technician_id ?? $user->id,
                    'status' => VisitStatus::Draft,
                ]);
            } else {
                $visit->update($fields);
            }

            if (array_key_exists('parts', $data)) {
                $keep = [];
                foreach ($data['parts'] as $part) {
                    $keep[] = strtolower($part['uuid']);
                    $visit->parts()->updateOrCreate(
                        ['uuid' => strtolower($part['uuid'])],
                        collect($part)->except('uuid')->all() + ['quantity' => $part['quantity'] ?? 1],
                    );
                }
                $visit->parts()->whereNotIn('uuid', $keep)->delete();
            }

            if (in_array($ticket->status, [TicketStatus::New, TicketStatus::Assigned], true)) {
                $ticket->changeStatus(TicketStatus::InProgress, $user);
            }
        });

        return $this->current($visit, $created ? 201 : 200);
    }

    public function show(Request $request, Visit $visit): VisitResource
    {
        abort_unless($visit->ticket && $visit->ticket->isVisibleTo($request->user()) && ! $request->user()->isCustomerOnly(), 403);

        return new VisitResource($visit->load(['technician', 'parts', 'attachments']));
    }

    /** Technik odešle návštěvu ke schválení */
    public function submit(Request $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        $ticket = $visit->ticket;
        abort_unless(($visit->technician_id === $user->id && $user->can('work', $ticket)) || $user->isAdmin(), 403);

        if ($visit->status === VisitStatus::Submitted || $visit->status === VisitStatus::Approved) {
            return $this->current($visit, 200); // opakované odeslání z offline fronty
        }

        $missing = [];
        foreach (['arrived_at', 'work_description', 'test_result'] as $field) {
            if (blank($visit->{$field})) {
                $missing[] = $field;
            }
        }
        if ($visit->km === null) {
            $missing[] = 'km';
        }
        if ($missing) {
            return response()->json(['message' => 'Chybí povinné údaje.', 'missing' => $missing], 422);
        }

        DB::transaction(function () use ($visit, $ticket, $user) {
            $visit->update(['status' => VisitStatus::Submitted, 'submitted_at' => now(), 'return_note' => null]);
            $ticket->changeStatus(TicketStatus::Completed, $user, 'Návštěva odeslána ke schválení');
        });

        return $this->current($visit, 200);
    }

    /** Dispečer vrátí návštěvu technikovi k doplnění */
    public function returnToTechnician(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('manage', $visit->ticket);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        abort_unless($visit->status === VisitStatus::Submitted, 422, 'Vrátit lze jen odeslanou návštěvu.');

        DB::transaction(function () use ($visit, $data, $request) {
            $visit->update(['status' => VisitStatus::Returned, 'return_note' => $data['note']]);
            $visit->ticket->protocols()->where('visit_id', $visit->id)->where('status', 'draft')->delete();
            $visit->ticket->changeStatus(TicketStatus::InProgress, $request->user(), 'Vráceno k doplnění: '.$data['note']);
        });

        app(TechnicianNotifier::class)->returned($visit->ticket, $visit->technician, $data['note']);

        return $this->current($visit, 200);
    }

    private function current(Visit $visit, int $status, ?string $message = null): JsonResponse
    {
        $payload = ['data' => (new VisitResource($visit->fresh()->load(['technician', 'parts', 'attachments'])))->resolve()];
        if ($message) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
