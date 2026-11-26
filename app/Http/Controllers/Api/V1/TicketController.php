<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\ChecklistTemplate;
use App\Models\Device;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mail\ClientReportMailer;
use App\Services\TechnicianNotifier;
use App\Services\TicketAdvisor;
use App\Services\TicketAssigner;
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
        if ($request->boolean('needs_review')) {
            $query->where('needs_review', true);
        }
        if (! empty($data['mine'])) {
            $query->where('assigned_technician_id', $user->id);
        }
        if (! empty($data['updated_since'])) {
            $query->where('updated_at', '>=', $data['updated_since']);
        }
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(fn ($w) => $w->where('number', 'like', $q)->orWhere('external_ref', 'like', $q)->orWhere('title', 'like', $q)
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
            'project', 'location', 'technician', 'attachments', 'protocols.visit', 'device', 'checklistTemplate', 'schedule:id,title,interval_months',
            'visits' => fn ($q) => $q->orderBy('id'),
            'visits.technician', 'visits.parts.part', 'visits.attachments', 'history.user', 'sourceEmail',
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
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'checklist_template_id' => ['nullable', 'integer', 'exists:checklist_templates,id'],
        ]);

        if (! empty($data['due_at'])) {
            $data['due_at'] = Carbon::parse($data['due_at'])->utc();
        }
        if (! empty($data['device_id'])) {
            abort_unless(Device::whereKey($data['device_id'])->value('project_id') === $ticket->project_id, 422, 'Zařízení nepatří do projektu zakázky.');
        }
        if (! empty($data['checklist_template_id'])) {
            abort_unless(ChecklistTemplate::whereKey($data['checklist_template_id'])->value('project_id') === $ticket->project_id, 422, 'Kontrolní seznam nepatří do projektu zakázky.');
        }

        if (array_key_exists('location_id', $data) && $data['location_id']) {
            $location = Location::findOrFail($data['location_id']);
            abort_unless($location->project_id === $ticket->project_id, 422, 'Provozovna nepatří do projektu zakázky.');
            $data['end_customer_id'] = $location->end_customer_id;
            $data['needs_review'] = false; // provozovna z e-mailu ověřena
        }

        $ticket->update($data);

        return new TicketResource($ticket->load(['project', 'location', 'technician']));
    }

    /** Uložené doporučení AI k závadě */
    public function advice(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAdvice($request, $ticket);

        return response()->json(['data' => $ticket->ai_advice, 'at' => $ticket->ai_advice_at?->toIso8601String(),
            'available' => app(TicketAdvisor::class)->available()]);
    }

    /** Nechat AI projít závadu (popis, zařízení, historie, podobné zakázky, návody) */
    public function generateAdvice(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAdvice($request, $ticket);
        @set_time_limit(90);
        $lang = $request->user()->locale === 'pl' ? 'pl' : 'cs';
        $advice = app(TicketAdvisor::class)->advise($ticket, $lang);

        return response()->json(['data' => $advice, 'at' => now()->toIso8601String(), 'available' => true]);
    }

    private function authorizeAdvice(Request $request, Ticket $ticket): void
    {
        $user = $request->user();
        $this->authorize('view', $ticket);
        abort_if($user->isCustomerOnly(), 403);
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
        if (array_key_exists('due_at', $data)) {
            $ticket->update(['due_at' => $data['due_at'] ? Carbon::parse($data['due_at'])->utc() : null]);
        }
        app(TicketAssigner::class)->assign($ticket, $technician, $request->user(), $data['note'] ?? null);

        return new TicketResource($ticket->refresh()->load(['project', 'location', 'technician']));
    }

    /** Návrh reportu klientovi (odpověď na původní e-mail) */
    public function clientReportDraft(Request $request, Ticket $ticket, ClientReportMailer $mailer): JsonResponse
    {
        $this->authorize('manage', $ticket);
        try {
            return response()->json(['data' => $mailer->draft($ticket)]);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }

    /** Odeslat report klientovi – popis, km, vybrané fotky, ve vlákně původního e-mailu (RITM) */
    public function clientReportSend(Request $request, Ticket $ticket, ClientReportMailer $mailer): TicketResource
    {
        $this->authorize('manage', $ticket);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'photos' => ['array', 'max:30'],
            'photos.*' => ['uuid'],
        ]);

        try {
            $mailer->send($ticket, $data['body'], $data['photos'] ?? [], $request->user());
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            abort(502, 'Odeslání se nepovedlo: '.$e->getMessage());
        }

        return new TicketResource($ticket->fresh()->load(['project', 'location', 'technician']));
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
