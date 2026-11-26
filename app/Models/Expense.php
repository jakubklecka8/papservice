<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORIES = ['fuel', 'parts', 'material', 'parking', 'toll', 'lodging', 'tools', 'other'];

    protected $fillable = ['uuid', 'user_id', 'project_id', 'ticket_id', 'category', 'amount', 'currency', 'spent_on', 'liters', 'paid_with',
        'description', 'receipt_path', 'receipt_mime', 'status', 'checked_by', 'checked_at', 'approved_by', 'approved_at', 'paid_by', 'paid_at',
        'return_note', 'payout_id'];

    protected $hidden = ['receipt_path'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'liters' => 'float', 'spent_on' => 'date', 'checked_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditableByOwner(): bool
    {
        return in_array($this->status, ['submitted', 'returned'], true);
    }
}
