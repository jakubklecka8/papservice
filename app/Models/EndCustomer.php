<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EndCustomer extends Model
{
    protected $fillable = ['project_id', 'code', 'name', 'countries', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['countries' => 'array', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
