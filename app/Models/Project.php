<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'service_types', 'countries', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['service_types' => 'array', 'countries' => 'array', 'is_active' => 'boolean'];
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
}
