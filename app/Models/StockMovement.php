<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    protected $fillable = [
        'uuid', 'type', 'part_id', 'quantity', 'from_warehouse_id', 'to_warehouse_id', 'serial_number',
        'ticket_id', 'visit_id', 'visit_part_id', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    protected static function booted(): void
    {
        static::creating(fn (StockMovement $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
