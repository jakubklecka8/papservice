<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Správa uživatelů – jen admin */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $users = User::query()->with(['roleRows', 'projects:id,code', 'locations:id'])->orderBy('name')->get();

        return response()->json(['data' => $users->map(fn (User $u) => $this->payload($u))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $this->validated($request, null);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? 'cs',
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncRelations($user, $data);

            return $user;
        });

        return response()->json(['data' => $this->payload($user->fresh(['roleRows', 'projects', 'locations']))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $this->validated($request, $user);

        // admin si nesmí sebrat vlastní admin roli ani se deaktivovat
        if ($user->id === $request->user()->id) {
            if (isset($data['roles']) && ! in_array(Role::Admin->value, $data['roles'], true)) {
                abort(422, 'Nemůžeš si odebrat roli administrátora.');
            }
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                abort(422, 'Nemůžeš deaktivovat sám sebe.');
            }
        }

        DB::transaction(function () use ($user, $data) {
            $fields = collect($data)->only(['name', 'phone', 'locale', 'is_active'])->all();
            if (! empty($data['email'])) {
                $fields['email'] = strtolower($data['email']);
            }
            if (! empty($data['password'])) {
                $fields['password'] = $data['password'];
            }
            $user->update($fields);
            $this->syncRelations($user, $data);

            // deaktivovaný uživatel se okamžitě odhlásí ze všech zařízení
            if ($user->is_active === false) {
                $user->tokens()->delete();
            }
        });

        return response()->json(['data' => $this->payload($user->fresh(['roleRows', 'projects', 'locations']))]);
    }

    private function validated(Request $request, ?User $user): array
    {
        return $request->validate([
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', Rule::in(['cs', 'pl'])],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => [$user ? 'sometimes' : 'required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
            'project_ids' => ['sometimes', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'location_ids' => ['sometimes', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
        ]);
    }

    private function syncRelations(User $user, array $data): void
    {
        if (isset($data['roles'])) {
            $user->syncRoles(array_map(fn ($r) => Role::from($r), $data['roles']));
        }
        if (array_key_exists('project_ids', $data)) {
            $user->projects()->sync($data['project_ids']);
        }
        if (array_key_exists('location_ids', $data)) {
            $user->locations()->sync($data['location_ids']);
        }
    }

    private function payload(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'locale' => $u->locale,
            'is_active' => (bool) $u->is_active,
            'roles' => $u->roleRows->sortBy('id')->pluck('role')->values(),
            'project_ids' => $u->projects->pluck('id')->values(),
            'location_ids' => $u->locations->pluck('id')->values(),
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
