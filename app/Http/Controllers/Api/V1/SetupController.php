<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceType;
use App\Http\Controllers\Api\V1\Concerns\ProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\EndCustomer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Správa projektů (admin) a kontrolních seznamů (4.1) */
class SetupController extends Controller
{
    use ProjectAccess;

    // ---------------------------------------------------------------- projekty

    public function projects(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => Project::with('endCustomers')->orderBy('name')->get()->map(fn (Project $p) => $this->projectPayload($p))]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $project = Project::create($this->validateProject($request, null));
        $this->syncEndCustomers($request, $project);

        return response()->json(['data' => $this->projectPayload($project->load('endCustomers'))], 201);
    }

    public function updateProject(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $project->update($this->validateProject($request, $project));
        $this->syncEndCustomers($request, $project);

        return response()->json(['data' => $this->projectPayload($project->fresh()->load('endCustomers'))]);
    }

    /** @return array<string, mixed> */
    private function validateProject(Request $request, ?Project $project): array
    {
        $req = $project ? 'sometimes' : 'required';
        $data = $request->validate([
            'code' => [$req, 'string', 'max:16', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('projects', 'code')->ignore($project?->id)],
            'name' => [$req, 'string', 'max:255'],
            'service_types' => [$req, 'array', 'min:1'],
            'service_types.*' => [Rule::enum(ServiceType::class)],
            'countries' => ['sometimes', 'array'],
            'countries.*' => ['string', 'size:2'],
            'protocol_template' => ['sometimes', Rule::in(['standard', 'm4b'])],
            'sla_hours' => ['nullable', 'array'],
            'sla_hours.*' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_active' => ['sometimes', 'boolean'],
            'end_customers' => ['sometimes', 'array'],
            'end_customers.*.id' => ['nullable', 'integer'],
            'end_customers.*.code' => ['required', 'string', 'max:32'],
            'end_customers.*.name' => ['required', 'string', 'max:255'],
            'end_customers.*.countries' => ['nullable', 'array'],
        ]);
        if (isset($data['sla_hours'])) {
            $data['sla_hours'] = array_filter(
                array_intersect_key($data['sla_hours'], array_flip(['urgent', 'high', 'normal', 'low'])),
                fn ($v) => $v !== null && $v !== ''
            ) ?: null;
        }
        if (isset($data['countries'])) {
            $data['countries'] = array_values(array_map('strtoupper', $data['countries']));
        }
        unset($data['end_customers']);

        return $data;
    }

    private function syncEndCustomers(Request $request, Project $project): void
    {
        if (! $request->has('end_customers')) {
            return;
        }
        foreach ($request->input('end_customers', []) as $row) {
            $attrs = ['code' => Str::upper($row['code']), 'name' => $row['name'], 'countries' => $row['countries'] ?? null];
            if (! empty($row['id'])) {
                EndCustomer::where('project_id', $project->id)->whereKey($row['id'])->first()?->update($attrs);
            } else {
                $project->endCustomers()->updateOrCreate(['code' => $attrs['code']], $attrs);
            }
        }
    }

    /** @return array<string, mixed> */
    private function projectPayload(Project $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'service_types' => $p->service_types ?? [],
            'countries' => $p->countries ?? [],
            'protocol_template' => $p->protocol_template ?? 'standard',
            'sla_hours' => $p->sla_hours ?? (object) [],
            'is_active' => (bool) $p->is_active,
            'end_customers' => $p->endCustomers->map(fn ($e) => ['id' => $e->id, 'code' => $e->code, 'name' => $e->name, 'countries' => $e->countries]),
        ];
    }

    // ---------------------------------------------------------------- kontrolní seznamy

    public function checklists(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canReadAllProjects() || $user->dispatcherProjectIds(), 403);
        $q = $this->scopeProjects(ChecklistTemplate::with('project:id,code,name'), $user)->orderBy('project_id')->orderBy('name');

        return response()->json(['data' => $q->get()->map(fn (ChecklistTemplate $c) => $this->checklistPayload($c))]);
    }

    public function storeChecklist(Request $request): JsonResponse
    {
        $data = $this->validateChecklist($request, null);
        $c = ChecklistTemplate::create($data);

        return response()->json(['data' => $this->checklistPayload($c->load('project'))], 201);
    }

    public function updateChecklist(Request $request, ChecklistTemplate $checklist): JsonResponse
    {
        $checklist->update($this->validateChecklist($request, $checklist));

        return response()->json(['data' => $this->checklistPayload($checklist->fresh()->load('project'))]);
    }

    public function destroyChecklist(Request $request, ChecklistTemplate $checklist): JsonResponse
    {
        abort_unless($this->canManageProject($request->user(), $checklist->project_id), 403);
        $checklist->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validateChecklist(Request $request, ?ChecklistTemplate $c): array
    {
        $req = $c ? 'sometimes' : 'required';
        $data = $request->validate([
            'project_id' => [$c ? 'prohibited' : 'required', 'integer', 'exists:projects,id'],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'name' => [$req, 'string', 'max:255'],
            'items' => [$req, 'array', 'min:1', 'max:100'],
            'items.*.id' => ['nullable', 'string', 'max:40'],
            'items.*.text' => ['required', 'string', 'max:500'],
            'items.*.required' => ['nullable', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        abort_unless($this->canManageProject($request->user(), $data['project_id'] ?? $c->project_id), 403);
        if (isset($data['items'])) {
            // stabilní id položek, aby staré vyplněné seznamy pořád seděly
            $data['items'] = array_values(array_map(fn ($i) => [
                'id' => $i['id'] ?? Str::lower(Str::random(8)),
                'text' => trim($i['text']),
                'required' => (bool) ($i['required'] ?? false),
            ], $data['items']));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function checklistPayload(ChecklistTemplate $c): array
    {
        return [
            'id' => $c->id,
            'project' => $c->project ? ['id' => $c->project->id, 'name' => $c->project->name] : null,
            'project_id' => $c->project_id,
            'service_type' => $c->service_type,
            'name' => $c->name,
            'items' => $c->items,
            'is_active' => $c->is_active,
        ];
    }
}
