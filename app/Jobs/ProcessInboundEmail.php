<?php

namespace App\Jobs;

use App\Models\InboundEmail;
use App\Services\Mail\InboundEmailProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // chyba se zapíše k e-mailu, dispečer ho zpracuje ručně

    public function __construct(public int $emailId) {}

    public function handle(InboundEmailProcessor $processor): void
    {
        if ($email = InboundEmail::find($this->emailId)) {
            $processor->process($email);
        }
    }
}
