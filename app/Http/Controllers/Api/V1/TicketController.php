<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TechnicianNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', 'string'],           // "new,assigned"
            'project_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],            // jen moje přidělené (technik)
            'open' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'updated_since' => ['nullable', 'date'],      // pro synchronizaci mobilní aplikace
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $user = $request->user();
        $query = Ticket::query()->visibleTo($user)->with(['project', 'location', 'technician']);

        if (! empty($data['status'])) {
            $query->whereIn('status', explode(',', $data['status']));
        }
        if (! empty($data['open'])) {
            $query->whereNotIn('status', [TicketStatus::Closed->value, TicketStatus::Cancelled->value]);
        }
        foreach (['project_id', 'location_id'] as $f) {
            if (! empty($data[$f])) {
                $query->where($f, $data[$f]);
            }
        }
        if (! empty($data['technician_id'])) {
            $query->where('assigned_technician_id', $data['technician_id']);
        }
        if (! empty($data['mine'])) {
            $query->where('assigned_technician_id', $user->id);
        }
        if (! empty($data['updated_since'])) {
            $query->where('updated_at', '>=', $data['updated_since']);
        }
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(fn ($w) => $w->where('number', 'like', $q)->orWhere('title', 'like', $q)
                ->orWhereHas('location', fn ($l) => $l->where('name', 'like', $q)->orWhere('code', 'like', $q)->orWhere('city', 'like', $q)));
        }

        return TicketResource::collection(
            $query->orderByDesc('id')->paginate($data['per_page'] ?? 50)
        );
    }

    /** Počty zakázek podle stavu (pro záložky v portálu) */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer']]);
        $query = Ticket::query()->visibleTo($request->user());
        if (! empty($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        }

        return response()->json(['data' => $query->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')]);
    }

    public function store(Request $request): TicketResource
    {
        $user = $request->user();
        $this->authorize('create', Ticket::class);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id', 'required_without:location_id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'reporter_phone' => ['nullable', 'string', 'max:32'],
            'uuid' => ['nullable', 'uuid'],
        ]);

        // Idempotence – opakované odeslání stejného formuláře nevytvoří druhý požadavek
        if (! empty($data['due_at'])) {
            $data['due_at'] = Carbon::parse($data['due_at'])->utc();
        }

        if (! empty($data['uuid']) && ($existing = Ticket::where('uuid', $data['uuid'])->first())) {
            abort_unless($existing->isVisibleTo($user), 403);

            return new TicketResource($existing->load(['project', 'location', 'technician']));
        }

        $location = ! empty($data['location_id']) ? Location::findOrFail($data['location_id']) : null;
        $projectId = $location?->project_id ?? (int) $data['project_id'];

        if ($location && ! empty($data['project_id']) && (int) $data['project_id'] !== $location->project_id) {
            abort(422, 'Provozovna nepatří do zvoleného projektu.');
        }

        $isInternal = $user->isAdmin() || in_array($projectId, $user->dispatcherProjectIds(), true);
        $isCustomer = $location && in_array($location->id, $user->customerLocationIds(), true);
        abort_unless($isInternal || $isCustomer, 403);

        $ticket = DB::transaction(function () use ($data, $user, $location, $projectId, $isInternal) {
            $ticket = Ticket::create([
                'uuid' => $data['uuid'] ?? null,
                'project_id' => $projectId,
                'end_customer_id' => $location?->end_customer_id,
                'location_id' => $location?->id,
                'service_type' => $data['service_type'] ?? ServiceType::Servis->value,
                'priority' => $isInternal ? ($data['priority'] ?? 'normal') : 'normal',
                'status' => TicketStatus::New,
                'source' => $isInternal ? 'manual' : 'portal',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => $isInternal ? ($data['due_at'] ?? null) : null,
                'created_by' => $user->id,
                'reporter_name' => $data['reporter_name'] ?? $user->name,
                'reporter_email' => $data['reporter_email'] ?? $user->email,
                'reporter_phone' => $data['reporter_phone'] ?? $user->phone,
            ]);
            $ticket->history()->create(['to_status' => TicketStatus::New->value, 'user_id' => $user->id]);

            return $ticket;
        });

        return new TicketResource($ticket->refresh()->load(['project', 'location', 'technician']));
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'project', 'location', 'technician', 'attachments', 'protocols.visit',
            'visits' => fn ($q) => $q->orderBy('id'),
            'visits.technician', 'visits.parts', 'visits.attachments', 'history.user',
        ]);

        return new TicketResource($ticket);
    }

    public function update(Request $request, Ticket $ticket): TicketResource
    {
        $this->authorize('manage', $ticket);

        $data = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'service_type' => ['sometimes', Rule::enum(ServiceType::class)],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['due_at'])) {
            $data['due_at'] = Carbon::parse($data['due_at'])->utc();
        }

        if (array_key_exists('location_id', $data) && $data['location_id']) {
            $location = Location::findOrFail($data['location_id']);
            abort_unless($location->project_id === $ticket->project_id, 422, 'Provozovna nepatří do projektu zakázky.');
            $data['end_customer_id'] = $location->end_customer_id;
        }

        $ticket->update($data);

        return new TicketResource($ticket->load(['project', 'location', 'technician']));
    }

    /** Přidělení technikovi dispečerem */
    public function assign(Request $request, Ticket $ticket): TicketResource
    {
        $this->authorize('manage', $ticket);

        $data = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $technician = User::findOrFail($data['technician_id']);
        abort_unless($technician->is_active && $technician->hasRole(Role::Technik), 422, 'Vybraný uživatel není aktivní technik.');
        abort_unless($ticket->status->isOpen(), 422, 'Uzavřenou zakázku nelze přidělit.');

        $previous = $ticket->assigned_technician_id;

        DB::transaction(function () use ($ticket, $technician, $request, $data) {
            $ticket->fill([
                'assigned_technician_id' => $technician->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ]);
            if (array_key_exists('due_at', $data)) {
                $ticket->due_at = $data['due_at'] ? Carbon::parse($data['due_at'])->utc() : null;
            }
            $ticket->save();

            if (in_array($ticket->status, [TicketStatus::New, TicketStatus::Assigned], true)) {
                $ticket->changeStatus(TicketStatus::Assigned, $request->user(), $data['note'] ?? 'Přiděleno: '.$technician->name);
            } else {
                $ticket->history()->create([
                    'from_status' => $ticket->status->value,
                    'to_status' => $ticket->status->value,
                    'user_id' => $request->user()->id,
                    'note' => 'Přeřazeno: '.$technician->name,
                ]);
            }
        });

        if ($previous !== $technician->id) {
            app(TechnicianNotifier::class)->assigned($ticket, $technician);
        }

        return new TicketResource($ticket->refresh()->load(['project', 'location', 'technician']));
    }

    /** Ruční změna stavu (čeká, zrušeno, znovu otevřeno). Uzavření jde přes schválení protokolu. */
    public function status(Request $request, Ticket $ticket): TicketResource
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'assigned', 'in_progress', 'waiting', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $to = TicketStatus::from($data['status']);

        $canManage = $user->can('manage', $ticket);
        // technik smí jen přepínat mezi "probíhá" a "čeká"
        $canWork = $user->can('work', $ticket) && in_array($to, [TicketStatus::InProgress, TicketStatus::Waiting], true);
        abort_unless($canManage || $canWork, 403);

        if ($to === TicketStatus::Assigned && ! $ticket->assigned_technician_id) {
            abort(422, 'Zakázka nemá přiděleného technika.');
        }

        $ticket->changeStatus($to, $user, $data['note'] ?? null);

        if ($to === TicketStatus::Cancelled && $ticket->technician && $ticket->assigned_technician_id !== $user->id) {
            app(TechnicianNotifier::class)->cancelled($ticket, $ticket->technician);
        }

        return new TicketResource($ticket->load(['project', 'location', 'technician']));
    }
}
