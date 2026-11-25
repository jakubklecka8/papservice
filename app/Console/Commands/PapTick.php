<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Jeden "tik" cronu – spouští se každou minutu z cronu hostingu
 * (CLI: php artisan pap:tick, nebo URL /_cron/{token}).
 *
 * Vše běží v jednom procesu, protože sdílený hosting nedovolí stálý worker
 * a z webového požadavku nejde spolehlivě spouštět další PHP procesy.
 */
class PapTick extends Command
{
    protected $signature = 'pap:tick {--seconds= : Max. doba zpracování fronty}';

    protected $description = 'Minutový cron: fronta, e-maily, údržba';

    public function handle(): int
    {
        $lock = Cache::lock('pap:tick', 120);
        if (! $lock->get()) {
            $this->info('Předchozí běh ještě neskončil.');

            return self::SUCCESS;
        }

        try {
            $seconds = (int) ($this->option('seconds') ?: config('pap.tick_seconds'));

            // M3: sem přibude import e-mailové schránky (IMAP) – před frontou,
            // aby se potvrzení nových požadavků odeslala ve stejném běhu.

            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => max(5, $seconds),
                '--tries' => 3,
                '--quiet' => true,
            ]);

            $this->daily('pap:daily:tokens', fn () => Artisan::call('sanctum:prune-expired', ['--hours' => 24]));
            $this->daily('pap:daily:failed', fn () => Artisan::call('queue:prune-failed', ['--hours' => 24 * 30]));

            Cache::put('pap:tick:last', now()->toIso8601String(), 86400);
            $this->info('OK');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function daily(string $key, callable $job): void
    {
        $today = now()->toDateString();
        if (Cache::get($key) !== $today) {
            Cache::put($key, $today, 172800);
            $job();
        }
    }
}
