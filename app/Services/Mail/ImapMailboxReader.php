<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/** Čtení schránky přes IMAP (čisté PHP, nepotřebuje rozšíření php-imap). Zprávy nechává nepřečtené. */
class ImapMailboxReader implements MailboxReader
{
    private function client(Mailbox $mb)
    {
        $client = (new ClientManager(['options' => ['fetch_order' => 'asc']]))->make([
            'host' => $mb->imap_host,
            'port' => $mb->imap_port ?: 993,
            'encryption' => $mb->imap_encryption === 'none' ? false : ($mb->imap_encryption ?: 'ssl'),
            'validate_cert' => true,
            'username' => $mb->imap_username,
            'password' => $mb->imap_password,
            'protocol' => 'imap',
            'timeout' => 30,
        ]);
        $client->connect();

        return $client;
    }

    public function test(Mailbox $mailbox): int
    {
        $client = $this->client($mailbox);
        try {
            $folder = $client->getFolderByPath($mailbox->imap_folder ?: 'INBOX');
            if (! $folder) {
                throw new RuntimeException('Složka '.$mailbox->imap_folder.' neexistuje.');
            }

            return (int) ($folder->examine()['exists'] ?? 0);
        } finally {
            $client->disconnect();
        }
    }

    public function fetchNew(Mailbox $mailbox, int $limit = 30): iterable
    {
        $client = $this->client($mailbox);
        try {
            $folder = $client->getFolderByPath($mailbox->imap_folder ?: 'INBOX');
            if (! $folder) {
                throw new RuntimeException('Složka '.$mailbox->imap_folder.' neexistuje.');
            }

            $query = $folder->query()->leaveUnread()->setFetchOrderAsc();
            if ($mailbox->last_uid > 0) {
                $messages = $query->getByUidGreater($mailbox->last_uid);
            } else {
                // první spuštění: jen zprávy od data založení schránky v systému
                $since = ($mailbox->import_since ?? now()->subDay())->copy()->startOfDay();
                $messages = $query->since($since)->get();
            }

            $out = [];
            foreach ($messages as $message) {
                $out[] = $this->parse($message);
            }
            usort($out, fn ($a, $b) => $a->uid <=> $b->uid);

            return array_slice($out, 0, $limit);
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // nic
            }
        }
    }

    private function parse(Message $m): ParsedEmail
    {
        $str = function ($attr): ?string {
            try {
                $v = $attr?->first() ?? null;
                if (is_object($v) && property_exists($v, 'mail')) {
                    return $v->mail;
                }

                return $v === null ? null : trim((string) $v);
            } catch (Throwable) {
                return null;
            }
        };

        $from = null;
        try {
            $from = $m->getFrom()->first();
        } catch (Throwable) {
            // nic
        }

        $text = $m->hasTextBody() ? $m->getTextBody() : $this->htmlToText($m->getHTMLBody());

        $attachments = [];
        foreach ($m->getAttachments() as $att) {
            $content = $att->getContent();
            if ($content === null || $content === '') {
                continue;
            }
            $attachments[] = [
                'name' => (string) ($att->getName() ?: 'priloha'),
                'mime' => (string) ($att->getMimeType() ?: 'application/octet-stream'),
                'content' => $content,
            ];
        }

        $messageId = $str($m->getMessageId()) ?: ('no-id-'.$m->getUid().'@'.md5((string) $str($m->getSubject())));
        $date = null;
        try {
            $d = $m->getDate()->first();
            $date = $d ? Carbon::parse((string) $d) : null;
        } catch (Throwable) {
            // nic
        }

        $refs = null;
        try {
            $refs = implode(' ', array_map('strval', $m->getReferences()->toArray()));
        } catch (Throwable) {
            // nic
        }

        return new ParsedEmail(
            uid: (int) $m->getUid(),
            messageId: $this->bracket($messageId),
            inReplyTo: ($r = $str($m->getInReplyTo())) ? $this->bracket($r) : null,
            references: $refs ?: null,
            fromEmail: $from?->mail ? strtolower($from->mail) : null,
            fromName: $from?->personal ?: null,
            subject: $str($m->getSubject()),
            text: trim($text),
            date: $date,
            attachments: $attachments,
        );
    }

    private function bracket(string $id): string
    {
        $id = trim($id);

        return str_starts_with($id, '<') ? $id : '<'.$id.'>';
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(br|\/p|\/div|\/tr|\/li)[^>]*>/i', "\n", $html);

        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
