<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instruction extends Model
{
    protected $fillable = ['project_id', 'device_type', 'title', 'tags', 'published_version_id', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InstructionVersion::class)->orderByDesc('version');
    }

    public function published(): BelongsTo
    {
        return $this->belongsTo(InstructionVersion::class, 'published_version_id');
    }
}
