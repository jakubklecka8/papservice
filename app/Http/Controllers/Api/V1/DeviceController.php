<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Evidence zařízení: karta, soupis sériových čísel, předání a historie (krok 08) */
class DeviceController extends Controller
{
    use ProjectAccess;

    public const EVENT_TYPES = ['install', 'handover', 'service', 'replace', 'remove', 'note'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'removed', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $q = $this->query($request, $data);

        return response()->json($q->paginate($data['per_page'] ?? 100)->through(fn (Device $d) => $this->payload($d)));
    }

    public function show(Request $request, Device $device): JsonResponse
    {
        $this->authorizeRead($request, $device);
        $device->load(['project:id,code,name', 'location:id,code,name,city', 'events.user:id,name', 'events.ticket:id,number,title']);
        $tickets = $device->tickets()->with('technician:id,name')->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => $this->payload($device) + [
            'events' => $device->events->map(fn (DeviceEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'happened_at' => $e->happened_at?->toIso8601String(),
                'user' => $e->user?->name,
                'ticket' => $e->ticket ? ['id' => $e->ticket->id, 'number' => $e->ticket->number] : null,
                'old_serial' => $e->old_serial,
                'new_serial' => $e->new_serial,
                'note' => $e->note,
            ]),
            'tickets' => $tickets->map(fn ($t) => [
                'id' => $t->id, 'number' => $t->number, 'title' => $t->title, 'status' => $t->status->value,
                'service_type' => $t->service_type, 'technician' => $t->technician?->name, 'created_at' => $t->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        abort_unless($this->canManageProject($request->user(), $data['project_id']), 403);

        $device = Device::create($data);
        $device->log('install', [
            'user_id' => $request->user()->id,
            'new_serial' => $device->serial_number,
            'note' => 'Zařízení zavedeno do evidence',
            'happened_at' => $device->installed_at ?? now(),
        ]);

        return response()->json(['data' => $this->payload($device->load(['project:id,code,name', 'location:id,code,name,city']))], 201);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        abort_unless($this->canManageProject($request->user(), $device->project_id), 403);
        $data = $this->validated($request, $device);
        $user = $request->user();

        $oldSerial = $device->serial_number;
        $oldLocation = $device->location_id;
        $wasHandover = $device->handover_at;
        $device->update($data);

        if (array_key_exists('serial_number', $data) && $oldSerial !== $device->serial_number) {
            $device->log('replace', ['user_id' => $user->id, 'old_serial' => $oldSerial, 'new_serial' => $device->serial_number, 'note' => 'Změna sériového čísla']);
        }
        if (array_key_exists('location_id', $data) && $oldLocation !== $device->location_id) {
            $device->log('note', ['user_id' => $user->id, 'note' => 'Přesun: '.($device->location?->name ?? 'bez provozovny')]);
        }
        if (! $wasHandover && $device->handover_at) {
            $device->log('handover', ['user_id' => $user->id, 'note' => 'Předáno: '.($device->handover_to ?: '—'), 'happened_at' => $device->handover_at]);
        }
        if (array_key_exists('status', $data) && $device->wasChanged('status') && $device->status === 'removed') {
            $device->log('remove', ['user_id' => $user->id, 'note' => 'Demontováno']);
        }

        return response()->json(['data' => $this->payload($device->fresh()->load(['project:id,code,name', 'location:id,code,name,city']))]);
    }

    /** Ruční záznam do historie (poznámka, předání, výměna…) */
    public function addEvent(Request $request, Device $device): JsonResponse
    {
        abort_unless($this->canManageProject($request->user(), $device->project_id), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(self::EVENT_TYPES)],
            'note' => ['nullable', 'string', 'max:2000'],
            'old_serial' => ['nullable', 'string', 'max:100'],
            'new_serial' => ['nullable', 'string', 'max:100'],
            'happened_at' => ['nullable', 'date'],
        ]);
        $event = $device->log($data['type'], [
            'user_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
            'old_serial' => $data['old_serial'] ?? null,
            'new_serial' => $data['new_serial'] ?? null,
            'happened_at' => isset($data['happened_at']) ? Carbon::parse($data['happened_at'])->utc() : now(),
        ]);
        if ($data['type'] === 'replace' && ! empty($data['new_serial'])) {
            $device->update(['serial_number' => $data['new_serial']]);
        }

        return response()->json(['data' => ['id' => $event->id]], 201);
    }

    /** Soupis sériových čísel (CSV pro Excel) */
    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer'], 'location_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'removed', 'all'])], 'q' => ['nullable', 'string', 'max:100'],
        ]);
        $rows = $this->query($request, $data)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Projekt', 'Kód provozovny', 'Provozovna', 'Město', 'Druh', 'Model', 'Sériové číslo', 'Stav', 'Instalace', 'Předání', 'Předáno komu', 'Poznámka'], ';');
            foreach ($rows as $d) {
                fputcsv($out, [
                    $d->project?->name, $d->location?->code, $d->location?->name, $d->location?->city, $d->type, $d->model, $d->serial_number,
                    $d->status === 'active' ? 'aktivní' : 'demontováno', $d->installed_at?->format('d.m.Y'), $d->handover_at?->format('d.m.Y'), $d->handover_to, $d->note,
                ], ';');
            }
            fclose($out);
        }, 'zarizeni_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request, array $data)
    {
        $user = $request->user();
        abort_unless($user->canReadAllProjects() || $user->dispatcherProjectIds(), 403);

        $q = $this->scopeProjects(Device::query()->with(['project:id,code,name', 'location:id,code,name,city']), $user)
            ->orderBy('location_id')->orderBy('type');
        if (! empty($data['project_id'])) {
            $q->where('project_id', $data['project_id']);
        }
        if (! empty($data['location_id'])) {
            $q->where('location_id', $data['location_id']);
        }
        $status = $data['status'] ?? 'active';
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if (! empty($data['q'])) {
            $like = '%'.$data['q'].'%';
            $q->where(fn ($w) => $w->where('serial_number', 'like', $like)->orWhere('type', 'like', $like)->orWhere('model', 'like', $like)
                ->orWhereHas('location', fn ($l) => $l->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('city', 'like', $like)));
        }

        return $q;
    }

    private function authorizeRead(Request $request, Device $device): void
    {
        $user = $request->user();
        $ok = $this->canReadProject($user, $device->project_id)
            && ($user->canReadAllProjects() || $user->dispatcherProjectIds());
        // technik vidí zařízení, na kterém má zakázku
        $ok = $ok || $device->tickets()->where('assigned_technician_id', $user->id)->exists();
        abort_unless($ok, 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Device $device): array
    {
        $req = $device ? 'sometimes' : 'required';
        $data = $request->validate([
            'project_id' => [$device ? 'prohibited' : 'required', 'integer', 'exists:projects,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'type' => [$req, 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'removed'])],
            'installed_at' => ['nullable', 'date'],
            'handover_at' => ['nullable', 'date'],
            'handover_to' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['location_id'])) {
            $projectId = $data['project_id'] ?? $device->project_id;
            abort_unless(Location::whereKey($data['location_id'])->value('project_id') === $projectId, 422, 'Provozovna nepatří do projektu zařízení.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function payload(Device $d): array
    {
        return [
            'id' => $d->id,
            'project' => $d->relationLoaded('project') && $d->project ? ['id' => $d->project->id, 'code' => $d->project->code, 'name' => $d->project->name] : null,
            'project_id' => $d->project_id,
            'location' => $d->relationLoaded('location') && $d->location ? ['id' => $d->location->id, 'code' => $d->location->code, 'name' => $d->location->name, 'city' => $d->location->city] : null,
            'location_id' => $d->location_id,
            'type' => $d->type,
            'model' => $d->model,
            'serial_number' => $d->serial_number,
            'status' => $d->status,
            'installed_at' => $d->installed_at?->toDateString(),
            'handover_at' => $d->handover_at?->toDateString(),
            'handover_to' => $d->handover_to,
            'note' => $d->note,
        ];
    }
}
