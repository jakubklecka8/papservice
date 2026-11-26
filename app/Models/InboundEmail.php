<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundEmail extends Model
{
    protected $fillable = [
        'mailbox_id', 'message_id', 'message_hash', 'in_reply_to', 'references', 'uid', 'from_email', 'from_name', 'subject',
        'body_text', 'attachments', 'received_at', 'status', 'ticket_id', 'ai_result', 'error', 'processed_at',
        'confirmation_sent_at', 'confirmation_message_id',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'ai_result' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public static function hash(string $messageId): string
    {
        return hash('sha256', strtolower(trim($messageId, " \t\r\n<>")));
    }

    /** @return list<string> všechna Message-ID, na která zpráva odpovídá */
    public function referencedIds(): array
    {
        $ids = [];
        foreach ([$this->in_reply_to, $this->references] as $v) {
            if ($v && preg_match_all('/<[^>]+>/', $v, $m)) {
                $ids = array_merge($ids, $m[0]);
            } elseif ($v) {
                $ids = array_merge($ids, preg_split('/\s+/', trim($v)));
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
