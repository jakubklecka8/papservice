<?php

namespace App\Services\Mail;

use App\Models\Mailbox;

interface MailboxReader
{
    /** @return iterable<ParsedEmail> nové zprávy (UID > last_uid, nejvýš $limit) */
    public function fetchNew(Mailbox $mailbox, int $limit = 30): iterable;

    /** Ověří přihlášení; vrací počet zpráv ve složce, nebo vyhodí výjimku */
    public function test(Mailbox $mailbox): int;

    /** @return list<string> názvy složek ve schránce (pro výběr složky Odeslané) */
    public function folders(Mailbox $mailbox): array;

    /** @return list<string> předměty zpráv ve složce od data (jen hlavičky) */
    public function subjectsSince(Mailbox $mailbox, string $folder, \DateTimeInterface $since): array;
}
