<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push do PWA. Klíče VAPID se vygenerují samy při prvním použití
 * a uloží do storage/app/private/vapid.json (nic se nemusí nastavovat v .env).
 */
class PushService
{
    private const KEY_FILE = 'vapid.json';

    /** @return array{publicKey: string, privateKey: string} */
    public function keys(): array
    {
        $disk = Storage::disk('local');
        if ($disk->exists(self::KEY_FILE)) {
            $keys = json_decode((string) $disk->get(self::KEY_FILE), true);
            if (! empty($keys['publicKey']) && ! empty($keys['privateKey'])) {
                return $keys;
            }
        }

        set_error_handler(fn () => true, E_USER_NOTICE | E_USER_DEPRECATED);
        try {
            $keys = VAPID::createVapidKeys();
        } finally {
            restore_error_handler();
        }
        $disk->put(self::KEY_FILE, json_encode($keys));

        return $keys;
    }

    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    /**
     * Pošle notifikaci na všechna zařízení uživatele. Neplatné odběry smaže.
     *
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     * @return int počet úspěšně doručených
     */
    public function sendToUser(User $user, array $payload): int
    {
        $subs = PushSubscription::where('user_id', $user->id)->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        // Knihovna bez GMP/BCMath hlásí E_USER_NOTICE „doporučujeme GMP“ – Laravel by z toho
        // udělal výjimku. Funguje i bez nich (jen pomaleji), takže upozornění ignorujeme.
        set_error_handler(fn () => true, E_USER_NOTICE | E_USER_DEPRECATED);
        try {
            return $this->send($subs, $payload);
        } finally {
            restore_error_handler();
        }
    }

    /** @param Collection<int, PushSubscription> $subs */
    private function send($subs, array $payload): int
    {

        $keys = $this->keys();
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('pap.vapid_subject') ?: config('app.url'),
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ],
        ], ['TTL' => 86400, 'urgency' => 'high']);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        foreach ($subs as $sub) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
                'contentEncoding' => $sub->content_encoding,
            ]), $body);
        }

        $ok = 0;
        try {
            foreach ($webPush->flush() as $report) {
                $hash = PushSubscription::hashEndpoint($report->getEndpoint());
                if ($report->isSuccess()) {
                    $ok++;
                    PushSubscription::where('endpoint_hash', $hash)->update(['last_sent_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', $hash)->delete();
                } else {
                    Log::warning('Push nedoručen: '.$report->getReason());
                }
            }
        } catch (Throwable $e) {
            Log::warning('Push selhal: '.$e->getMessage());
        }

        return $ok;
    }
}
