<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'service_types', 'countries', 'protocol_template', 'sla_hours', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['service_types' => 'array', 'countries' => 'array', 'sla_hours' => 'array', 'is_active' => 'boolean'];
    }

    public function endCustomers(): HasMany
    {
        return $this->hasMany(EndCustomer::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** Termín podle SLA projektu a priority (null = projekt SLA nemá) */
    public function slaDueAt(string $priority, ?\DateTimeInterface $from = null): ?\Illuminate\Support\Carbon
    {
        $hours = $this->sla_hours[$priority] ?? null;

        return $hours ? \Illuminate\Support\Carbon::instance($from ?? now())->addHours((int) $hours) : null;
    }
}
