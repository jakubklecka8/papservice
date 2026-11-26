<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessageId extends Model
{
    protected $fillable = ['ticket_id', 'message_id', 'message_hash'];

    public static function remember(int $ticketId, string $messageId): void
    {
        static::firstOrCreate(
            ['message_hash' => InboundEmail::hash($messageId)],
            ['ticket_id' => $ticketId, 'message_id' => mb_substr($messageId, 0, 512)],
        );
    }

    public static function ticketFor(array $messageIds): ?int
    {
        if (! $messageIds) {
            return null;
        }

        return static::whereIn('message_hash', array_map([InboundEmail::class, 'hash'], $messageIds))->value('ticket_id');
    }
}
