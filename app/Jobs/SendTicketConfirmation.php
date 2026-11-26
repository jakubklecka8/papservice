<?php

namespace App\Jobs;

use App\Models\InboundEmail;
use App\Services\Mail\ConfirmationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Potvrzení s číslem požadavku – nejvýš jedno na e-mail (i při opakování fronty) */
class SendTicketConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $emailId) {}

    public function handle(ConfirmationSender $sender): void
    {
        $lock = Cache::lock('confirm-email-'.$this->emailId, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            $email = InboundEmail::find($this->emailId);
            if ($email && $email->confirmation_sent_at === null && in_array($email->status, ['ticket', 'review'], true)) {
                $sender->send($email);
            }
        } finally {
            $lock->release();
        }
    }
}
