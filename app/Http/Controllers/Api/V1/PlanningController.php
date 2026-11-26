<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Controllers\Api\V1\Concerns\ProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\TechnicianAbsence;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ScheduleService;
use App\Services\TicketAssigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Plánování: kalendář techniků, dostupnost a harmonogramy (krok 09) */
class PlanningController extends Controller
{
    use ProjectAccess;

    private const TZ = 'Europe/Prague';

    /** Týden/období: technici, naplánované a nenaplánované zakázky, nepřítomnosti */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->requireDispatch($user);
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'project_id' => ['nullable', 'integer'],
        ]);
        $from = Carbon::parse($data['from'], self::TZ)->startOfDay();
        $to = Carbon::parse($data['to'], self::TZ)->endOfDay();
        abort_if($from->diffInDays($to) > 62, 422, 'Max. 2 měsíce.');

        $base = fn () => $this->scopeProjects(Ticket::query(), $user)
            ->when($data['project_id'] ?? null, fn ($q, $p) => $q->where('project_id', $p))
            ->with(['location:id,code,name,city', 'project:id,code,name', 'technician:id,name']);

        $planned = $base()->whereBetween('planned_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->where('status', '!=', TicketStatus::Cancelled->value)->orderBy('planned_at')->get();

        $unplanned = $base()->whereNull('planned_at')
            ->whereIn('status', [TicketStatus::New->value, TicketStatus::Assigned->value, TicketStatus::InProgress->value, TicketStatus::Waiting->value])
            ->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id')->limit(200)->get();

        $techQuery = User::query()->where('is_active', true)
            ->whereHas('roleRows', fn ($q) => $q->where('role', Role::Technik->value))->orderBy('name');
        if ($pid = $data['project_id'] ?? null) {
            $techQuery->whereHas('projects', fn ($q) => $q->where('projects.id', $pid));
        } elseif (! $user->canReadAllProjects()) {
            $techQuery->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $user->dispatcherProjectIds()));
        }
        $techs = $techQuery->get(['id', 'name']);
        // technik, který má v období zakázku, se ukáže i když není v projektu
        $extra = $planned->pluck('technician')->filter()->reject(fn ($t) => $techs->contains('id', $t->id))->unique('id');
        $techs = $techs->concat($extra->map(fn ($t) => (object) ['id' => $t->id, 'name' => $t->name]))->values();

        $absences = TechnicianAbsence::whereIn('user_id', $techs->pluck('id'))
            ->whereDate('date_from', '<=', $to->toDateString())->whereDate('date_to', '>=', $from->toDateString())->get();

        return response()->json([
            'technicians' => $techs->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            'planned' => $planned->map(fn (Ticket $t) => $this->ticketRow($t)),
            'unplanned' => $unplanned->map(fn (Ticket $t) => $this->ticketRow($t)),
            'absences' => $absences->map(fn ($a) => ['id' => $a->id, 'user_id' => $a->user_id, 'from' => $a->date_from->toDateString(), 'to' => $a->date_to->toDateString(), 'note' => $a->note]),
        ]);
    }

    /** Naplánovat zakázku: technik + den/čas (planned_at null = zrušit plán) */
    public function plan(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('manage', $ticket);
        $data = $request->validate([
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'planned_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['technician_id']) && $data['technician_id'] !== $ticket->assigned_technician_id) {
            app(TicketAssigner::class)->assign($ticket, User::findOrFail($data['technician_id']), $request->user());
        }
        if (array_key_exists('planned_at', $data)) {
            $planned = $data['planned_at'] ? Carbon::parse($data['planned_at'])->utc() : null;
            $ticket->refresh()->update(['planned_at' => $planned]);
            $ticket->history()->create([
                'from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'user_id' => $request->user()->id,
                'note' => $planned ? 'Naplánováno na '.$planned->copy()->timezone(self::TZ)->format('d.m.Y H:i') : 'Plán zrušen',
            ]);
            if ($planned && $ticket->technician) {
                app(\App\Services\TechnicianNotifier::class)->planned($ticket, $ticket->technician);
            }
        }

        return response()->json(['data' => $this->ticketRow($ticket->fresh()->load(['location', 'project', 'technician']))]);
    }

    // ---------------------------------------------------------------- nepřítomnosti

    public function storeAbsence(Request $request): JsonResponse
    {
        $this->requireDispatch($request->user());
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $a = TechnicianAbsence::create($data);

        return response()->json(['data' => ['id' => $a->id]], 201);
    }

    public function destroyAbsence(Request $request, TechnicianAbsence $absence): JsonResponse
    {
        $this->requireDispatch($request->user());
        $absence->delete();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- harmonogramy

    public function schedules(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->requireDispatch($user);
        $q = $this->scopeProjects(Schedule::query(), $user)
            ->with(['project:id,code,name', 'location:id,code,name,city', 'device:id,type,serial_number', 'technician:id,name', 'checklist:id,name'])
            ->when($request->integer('project_id'), fn ($q, $p) => $q->where('project_id', $p))
            ->orderBy('next_due_on');

        return response()->json(['data' => $q->get()->map(fn (Schedule $s) => $this->schedulePayload($s))]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $data = $this->validateSchedule($request, null);
        $s = Schedule::create($data);
        // když je termín už v dosahu, rovnou založ zakázku
        app(ScheduleService::class)->run();

        return response()->json(['data' => $this->schedulePayload($s->fresh()->load(['project', 'location', 'device', 'technician', 'checklist']))], 201);
    }

    public function updateSchedule(Request $request, Schedule $schedule): JsonResponse
    {
        $schedule->update($this->validateSchedule($request, $schedule));

        return response()->json(['data' => $this->schedulePayload($schedule->fresh()->load(['project', 'location', 'device', 'technician', 'checklist']))]);
    }

    public function destroySchedule(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless($this->canManageProject($request->user(), $schedule->project_id), 403);
        $schedule->delete();

        return response()->json(['ok' => true]);
    }

    /** Založit zakázku hned (bez čekání na termín) */
    public function runSchedule(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless($this->canManageProject($request->user(), $schedule->project_id), 403);
        $ticket = app(ScheduleService::class)->generate($schedule);

        return response()->json(['data' => ['ticket_id' => $ticket->id, 'number' => $ticket->number]], 201);
    }

    // ----------------------------------------------------------------

    private function requireDispatch(User $user): void
    {
        abort_unless($user->isAdmin() || $user->hasRole(Role::Dispecer) || $user->canReadAllProjects(), 403);
    }

    /** @return array<string, mixed> */
    private function validateSchedule(Request $request, ?Schedule $schedule): array
    {
        $req = $schedule ? 'sometimes' : 'required';
        $data = $request->validate([
            'location_id' => [$req, 'integer', 'exists:locations,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'checklist_template_id' => ['nullable', 'integer', 'exists:checklist_templates,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_type' => [$req, Rule::in(['servis', 'profilaxe', 'udrzba', 'instalace'])],
            'title' => [$req, 'string', 'max:255'],
            'interval_months' => [$req, 'integer', 'min:1', 'max:60'],
            'next_due_on' => [$req, 'date'],
            'lead_days' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $locationId = $data['location_id'] ?? $schedule?->location_id;
        $projectId = \App\Models\Location::whereKey($locationId)->value('project_id');
        abort_unless($projectId && $this->canManageProject($request->user(), $projectId), 403);
        if ($schedule && $schedule->project_id !== $projectId) {
            abort(422, 'Provozovna musí zůstat ve stejném projektu.');
        }
        $data['project_id'] = $projectId;

        return $data;
    }

    /** @return array<string, mixed> */
    private function schedulePayload(Schedule $s): array
    {
        return [
            'id' => $s->id,
            'project' => $s->project ? ['id' => $s->project->id, 'name' => $s->project->name] : null,
            'location' => $s->location ? ['id' => $s->location->id, 'code' => $s->location->code, 'name' => $s->location->name, 'city' => $s->location->city] : null,
            'device' => $s->device ? ['id' => $s->device->id, 'type' => $s->device->type, 'serial_number' => $s->device->serial_number] : null,
            'technician' => $s->technician ? ['id' => $s->technician->id, 'name' => $s->technician->name] : null,
            'checklist' => $s->checklist ? ['id' => $s->checklist->id, 'name' => $s->checklist->name] : null,
            'service_type' => $s->service_type,
            'title' => $s->title,
            'interval_months' => $s->interval_months,
            'next_due_on' => $s->next_due_on?->toDateString(),
            'lead_days' => $s->lead_days,
            'is_active' => $s->is_active,
            'last_generated_at' => $s->last_generated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function ticketRow(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'number' => $t->number,
            'external_ref' => $t->external_ref,
            'title' => $t->title,
            'status' => $t->status->value,
            'priority' => $t->priority,
            'service_type' => $t->service_type,
            'project' => $t->project?->name,
            'project_id' => $t->project_id,
            'location' => $t->location ? trim(($t->location->code ? $t->location->code.' · ' : '').$t->location->name) : null,
            'city' => $t->location?->city,
            'technician_id' => $t->assigned_technician_id,
            'technician' => $t->technician?->name,
            'planned_at' => $t->planned_at?->toIso8601String(),
            'due_at' => $t->due_at?->toIso8601String(),
        ];
    }
}
