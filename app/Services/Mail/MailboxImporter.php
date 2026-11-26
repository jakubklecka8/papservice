<?php

namespace App\Services\Mail;

use App\Jobs\ProcessInboundEmail;
use App\Models\InboundEmail;
use App\Models\Mailbox;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Načte nové zprávy ze schránky a uloží je (text + přílohy). Každá zpráva se uloží
 * jen jednou – podle Message-ID; opakované načtení nic nezdvojí.
 */
class MailboxImporter
{
    public function __construct(private readonly MailboxReader $reader) {}

    /** @return int počet nově uložených zpráv */
    public function import(Mailbox $mailbox): int
    {
        $new = 0;
        try {
            foreach ($this->reader->fetchNew($mailbox) as $msg) {
                if ($this->store($mailbox, $msg)) {
                    $new++;
                }
                if ($msg->uid > $mailbox->last_uid) {
                    $mailbox->last_uid = $msg->uid;
                }
            }
            $mailbox->last_error = null;
        } catch (Throwable $e) {
            report($e);
            $mailbox->last_error = Str::limit($e->getMessage(), 500);
        }

        $mailbox->last_checked_at = now();
        $mailbox->save();

        return $new;
    }

    public function store(Mailbox $mailbox, ParsedEmail $msg): ?InboundEmail
    {
        $hash = InboundEmail::hash($msg->messageId);
        if (InboundEmail::where('mailbox_id', $mailbox->id)->where('message_hash', $hash)->exists()) {
            return null;
        }

        $dir = 'emails/'.$mailbox->id.'/'.substr($hash, 0, 16);
        $files = [];
        foreach ($msg->attachments as $i => $a) {
            $ext = strtolower(pathinfo($a['name'], PATHINFO_EXTENSION)) ?: 'bin';
            $path = $dir.'/'.($i + 1).'-'.Str::slug(pathinfo($a['name'], PATHINFO_FILENAME)).'.'.$ext;
            Storage::disk('local')->put($path, $a['content']);
            $files[] = ['path' => $path, 'name' => mb_substr($a['name'], 0, 255), 'mime' => $a['mime'], 'size' => strlen($a['content'])];
        }

        $email = InboundEmail::create([
            'mailbox_id' => $mailbox->id,
            'message_id' => mb_substr($msg->messageId, 0, 512),
            'message_hash' => $hash,
            'in_reply_to' => $msg->inReplyTo ? mb_substr($msg->inReplyTo, 0, 512) : null,
            'references' => $msg->references,
            'uid' => $msg->uid,
            'from_email' => $msg->fromEmail,
            'from_name' => $msg->fromName ? mb_substr($msg->fromName, 0, 255) : null,
            'subject' => $msg->subject ? mb_substr($msg->subject, 0, 1000) : null,
            'body_text' => $msg->text,
            'attachments' => $files,
            'received_at' => $msg->date ?? now(),
            'status' => 'new',
        ]);

        ProcessInboundEmail::dispatch($email->id);

        return $email;
    }
}
