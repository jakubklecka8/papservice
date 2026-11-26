<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Project::query()->where('is_active', true)->with('endCustomers:id,project_id,code,name')->orderBy('name');

        if (! $user->canReadAllProjects()) {
            $ids = array_unique(array_merge(
                $user->dispatcherProjectIds(),
                Location::whereIn('id', $user->customerLocationIds())->pluck('project_id')->all(),
            ));
            $query->whereIn('id', $ids);
        }

        return response()->json(['data' => $query->get(['id', 'code', 'name', 'service_types', 'countries'])]);
    }

    public function locations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'all' => ['nullable', 'boolean'],          // i neaktivní (správa provozoven)
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = Location::query()->visibleTo($request->user())->orderBy('name');
        if (empty($data['all'])) {
            $query->where('is_active', true);
        }
        if (! empty($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        }
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(fn ($w) => $w->where('name', 'like', $q)->orWhere('code', 'like', $q)->orWhere('external_id', 'like', $q)->orWhere('city', 'like', $q));
        }

        return response()->json($query->paginate($data['per_page'] ?? 50, [
            'id', 'project_id', 'end_customer_id', 'code', 'external_id', 'name', 'street', 'city', 'zip', 'country', 'lat', 'lng', 'note', 'is_active',
        ]));
    }

    /** Technici pro přidělení – jen pro dispečera/admina */
    public function technicians(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::Admin, Role::Dispecer), 403);

        $data = $request->validate(['project_id' => ['nullable', 'integer']]);

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('roleRows', fn ($q) => $q->where('role', Role::Technik->value))
            ->orderBy('name');

        if (! empty($data['project_id'])) {
            $query->whereHas('projects', fn ($q) => $q->where('projects.id', $data['project_id']));
        }

        return response()->json(['data' => $query->get(['id', 'name', 'phone', 'locale'])]);
    }
}
