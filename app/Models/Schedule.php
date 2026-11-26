<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $fillable = ['project_id', 'location_id', 'device_id', 'checklist_template_id', 'technician_id', 'service_type', 'title',
        'interval_months', 'next_due_on', 'lead_days', 'is_active', 'last_generated_at'];

    protected function casts(): array
    {
        return ['next_due_on' => 'date', 'is_active' => 'boolean', 'last_generated_at' => 'datetime', 'interval_months' => 'integer', 'lead_days' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
