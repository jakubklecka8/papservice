<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    protected $table = 'ticket_status_history';

    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'from_status', 'to_status', 'user_id', 'note', 'created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
