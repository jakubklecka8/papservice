<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'uuid', 'attachable_type', 'attachable_id', 'uploaded_by', 'kind', 'disk', 'path',
        'original_name', 'mime', 'size', 'customer_visible', 'caption', 'legacy_id',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['customer_visible' => 'boolean', 'size' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Zakázka, ke které příloha patří (přímo nebo přes návštěvu) */
    public function ticket(): ?Ticket
    {
        $parent = $this->attachable;

        return match (true) {
            $parent instanceof Ticket => $parent,
            $parent instanceof Visit => $parent->ticket,
            default => null,
        };
    }
}
