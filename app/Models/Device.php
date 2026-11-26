<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = ['project_id', 'location_id', 'type', 'model', 'serial_number', 'status', 'installed_at', 'handover_at', 'handover_to', 'note', 'legacy_id'];

    protected function casts(): array
    {
        return ['installed_at' => 'date', 'handover_at' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class)->orderByDesc('happened_at')->orderByDesc('id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function log(string $type, array $attrs = []): DeviceEvent
    {
        return $this->events()->create($attrs + ['type' => $type, 'happened_at' => now()]);
    }
}
