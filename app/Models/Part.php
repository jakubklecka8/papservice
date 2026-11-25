<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Part extends Model
{
    protected $fillable = ['project_id', 'part_number', 'name', 'unit', 'serialized', 'note', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['serialized' => 'boolean', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
