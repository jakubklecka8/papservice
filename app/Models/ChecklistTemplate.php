<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplate extends Model
{
    protected $fillable = ['project_id', 'service_type', 'name', 'items', 'is_active'];

    protected function casts(): array
    {
        return ['items' => 'array', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Výchozí seznam pro projekt a typ služby (konkrétní typ má přednost před obecným) */
    public static function for(int $projectId, ?string $serviceType): ?self
    {
        return static::where('project_id', $projectId)->where('is_active', true)
            ->where(fn ($q) => $q->where('service_type', $serviceType)->orWhereNull('service_type'))
            ->orderByRaw('service_type is null')->orderBy('id')
            ->first();
    }
}
