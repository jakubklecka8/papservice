<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'home_address', 'home_lat', 'home_lng', 'locale', 'settlement_mode', 'ico', 'is_active', 'legacy_id'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string, true>|null */
    private ?array $roleCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roleRows(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /** Provozovny přiřazené zákazníkovi */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    /** @return list<string> */
    public function roles(): array
    {
        if ($this->roleCache === null) {
            $this->roleCache = $this->roleRows()->pluck('role')->flip()->map(fn () => true)->all();
        }

        return array_keys($this->roleCache);
    }

    public function hasRole(Role ...$roles): bool
    {
        $mine = $this->roles();
        foreach ($roles as $role) {
            if (in_array($role->value, $mine, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<Role>  $roles */
    public function syncRoles(array $roles): void
    {
        $values = array_values(array_unique(array_map(fn (Role $r) => $r->value, $roles)));
        $this->roleRows()->whereNotIn('role', $values)->delete();
        foreach ($values as $value) {
            $this->roleRows()->firstOrCreate(['role' => $value]);
        }
        $this->roleCache = null;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    /** Čtení všech projektů bez omezení (admin, ekonom, společník) */
    public function canReadAllProjects(): bool
    {
        return $this->hasRole(Role::Admin, Role::Ekonom, Role::Spolecnik);
    }

    /** @return list<int> */
    public function dispatcherProjectIds(): array
    {
        if (! $this->hasRole(Role::Dispecer)) {
            return [];
        }

        return $this->projects()->pluck('projects.id')->all();
    }

    /** @return list<int> */
    public function customerLocationIds(): array
    {
        if (! $this->hasRole(Role::Zakaznik)) {
            return [];
        }

        return $this->locations()->pluck('locations.id')->all();
    }

    /** Uživatel je jen zákazník – nevidí interní data (neschválené protokoly, interní fotky) */
    public function isCustomerOnly(): bool
    {
        return $this->roles() === [Role::Zakaznik->value];
    }
}
