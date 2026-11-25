<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Notifikace jde přes frontu – odešle ji minutový cron, request dispečera se nezdržuje. */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array{title: string, body: string, url?: string, tag?: string} $payload */
    public function __construct(public int $userId, public array $payload) {}

    public function handle(PushService $push): void
    {
        $user = User::find($this->userId);
        if ($user && $user->is_active) {
            $push->sendToUser($user, $this->payload);
        }
    }
}
