<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'end_customer_id', 'code', 'external_id', 'name', 'street', 'city', 'zip', 'country',
        'lat', 'lng', 'note', 'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function endCustomer(): BelongsTo
    {
        return $this->belongsTo(EndCustomer::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Provozovny, které uživatel smí vidět / vybrat */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canReadAllProjects()) {
            return $query;
        }

        $projectIds = $user->dispatcherProjectIds();
        $locationIds = $user->customerLocationIds();

        return $query->where(function (Builder $q) use ($projectIds, $locationIds, $user) {
            $q->whereRaw('1 = 0');
            if ($projectIds) {
                $q->orWhereIn('project_id', $projectIds);
            }
            if ($locationIds) {
                $q->orWhereIn('id', $locationIds);
            }
            // technik vidí provozovny svých zakázek
            $q->orWhereIn('id', Ticket::query()->select('location_id')
                ->where('assigned_technician_id', $user->id)->whereNotNull('location_id'));
        });
    }
}
