<?php

namespace App\Services\Mail;

use Illuminate\Support\Carbon;

/** Zpráva načtená ze schránky – nezávisle na IMAP knihovně (kvůli testům) */
class ParsedEmail
{
    /**
     * @param  list<array{name: string, mime: string, content: string}>  $attachments
     */
    public function __construct(
        public int $uid,
        public string $messageId,
        public ?string $inReplyTo,
        public ?string $references,
        public ?string $fromEmail,
        public ?string $fromName,
        public ?string $subject,
        public string $text,
        public ?Carbon $date,
        public array $attachments = [],
    ) {}
}
