<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mailbox extends Model
{
    protected $fillable = [
        'name', 'project_id', 'parser', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder', 'sent_folder',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'from_address', 'from_name',
        'dedicated', 'auto_reply', 'is_active', 'import_since', 'last_uid', 'last_checked_at', 'last_error',
    ];

    protected $hidden = ['imap_password', 'smtp_password'];

    protected function casts(): array
    {
        return [
            'imap_password' => 'encrypted',
            'smtp_password' => 'encrypted',
            'dedicated' => 'boolean',
            'auto_reply' => 'boolean',
            'is_active' => 'boolean',
            'import_since' => 'datetime',
            'last_checked_at' => 'datetime',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'last_uid' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(InboundEmail::class);
    }

    public function canSend(): bool
    {
        return filled($this->smtp_host) && filled($this->smtp_username) && filled($this->smtp_password);
    }
}
