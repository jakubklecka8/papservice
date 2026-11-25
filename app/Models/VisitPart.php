<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitPart extends Model
{
    protected $fillable = ['uuid', 'visit_id', 'name', 'part_number', 'quantity', 'serial_removed', 'serial_installed', 'note'];

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
