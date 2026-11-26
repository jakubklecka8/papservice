<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tariff extends Model
{
    protected $fillable = ['project_id', 'service_type', 'currency', 'base_price', 'km_rate', 'note'];

    protected function casts(): array
    {
        return ['base_price' => 'float', 'km_rate' => 'float'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Ceník pro projekt a typ služby; když typ nemá vlastní řádek, platí obecný (service_type = null) */
    public static function for(int $projectId, ?string $serviceType): ?self
    {
        return static::where('project_id', $projectId)
            ->where(fn ($q) => $q->where('service_type', $serviceType)->orWhereNull('service_type'))
            ->orderByRaw('service_type is null')
            ->first();
    }
}
