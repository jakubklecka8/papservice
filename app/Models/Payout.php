<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    protected $fillable = ['user_id', 'month', 'currency', 'total', 'data', 'status', 'confirmed_by', 'confirmed_at', 'paid_by', 'paid_at', 'note'];

    protected function casts(): array
    {
        return ['data' => 'array', 'total' => 'float', 'confirmed_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
