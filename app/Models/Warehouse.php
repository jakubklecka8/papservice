<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'type', 'project_id', 'end_customer_id', 'user_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Sklad technika – založí se automaticky při první potřebě */
    public static function forTechnician(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            ['code' => 'TECH-'.$user->id, 'name' => $user->name, 'type' => 'technician'],
        );
    }

    /** Které sklady uživatel vidí: interní role všechny, technik jen svůj */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasRole(Role::Admin, Role::Dispecer, Role::Ekonom, Role::Spolecnik)) {
            return $q;
        }

        return $q->where('user_id', $user->id);
    }
}
