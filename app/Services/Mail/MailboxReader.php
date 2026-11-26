<?php

namespace App\Services\Mail;

use App\Models\Mailbox;

interface MailboxReader
{
    /** @return iterable<ParsedEmail> nové zprávy (UID > last_uid, nejvýš $limit) */
    public function fetchNew(Mailbox $mailbox, int $limit = 30): iterable;

    /** Ověří přihlášení; vrací počet zpráv ve složce, nebo vyhodí výjimku */
    public function test(Mailbox $mailbox): int;
}
