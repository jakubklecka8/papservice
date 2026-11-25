<?php

namespace App\Models;

use App\Enums\TestResult;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Visit extends Model
{
    protected $fillable = [
        'uuid', 'ticket_id', 'technician_id', 'status', 'arrived_at', 'departed_at', 'minutes_on_site', 'km',
        'work_description', 'repair_description', 'test_result', 'test_note', 'instruction_ref',
        'submitted_at', 'return_note', 'client_updated_at', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'test_result' => TestResult::class,
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'client_updated_at' => 'datetime',
            'km' => 'float',
            'minutes_on_site' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(VisitPart::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [VisitStatus::Draft, VisitStatus::Returned], true);
    }
}
