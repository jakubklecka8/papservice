<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EndCustomer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Zakládání a úprava provozoven – admin nebo dispečer daného projektu */
class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $this->ensureManager($request->user(), (int) $data['project_id']);

        $location = Location::create($data);

        return response()->json(['data' => $location], 201);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $this->ensureManager($request->user(), $location->project_id);
        $data = $this->validated($request, $location);
        if (isset($data['project_id']) && (int) $data['project_id'] !== $location->project_id) {
            abort(422, 'Projekt provozovny nejde změnit.');
        }

        $location->update($data);

        return response()->json(['data' => $location->fresh()]);
    }

    private function validated(Request $request, ?Location $location): array
    {
        $projectId = $location?->project_id ?? (int) $request->input('project_id');

        $data = $request->validate([
            'project_id' => [$location ? 'sometimes' : 'required', 'integer', 'exists:projects,id'],
            'end_customer_id' => ['nullable', 'integer', 'exists:end_customers,id'],
            'code' => [$location ? 'sometimes' : 'required', 'string', 'max:64',
                Rule::unique('locations')->where('project_id', $projectId)->ignore($location?->id)],
            'name' => [$location ? 'sometimes' : 'required', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', Rule::in(['CZ', 'PL', 'DE', 'SK'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['end_customer_id'])) {
            abort_unless(EndCustomer::whereKey($data['end_customer_id'])->where('project_id', $projectId)->exists(), 422, 'Koncový zákazník nepatří do projektu.');
        }

        return $data;
    }

    private function ensureManager(User $user, int $projectId): void
    {
        abort_unless($user->isAdmin() || in_array($projectId, $user->dispatcherProjectIds(), true), 403);
    }
}
