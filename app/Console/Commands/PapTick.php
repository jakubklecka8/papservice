<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\Mail\MailboxImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

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

            // e-mailové schránky (každé 2 minuty) – před frontou, aby se nové
            // požadavky zpracovaly a potvrdily ve stejném běhu
            if (! Cache::has('pap:mail:recent')) {
                Cache::put('pap:mail:recent', true, 110);
                foreach (Mailbox::where('is_active', true)->get() as $mailbox) {
                    try {
                        app(MailboxImporter::class)->import($mailbox);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            }

            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => max(5, $seconds),
                '--tries' => 3,
                '--quiet' => true,
            ]);

            // úklid – přímo dotazy, ne Artisan příkazy balíčků (ty přes web/URL cron neexistují)
            $this->daily('pap:daily:tokens', fn () => PersonalAccessToken::query()
                ->whereNotNull('expires_at')->where('expires_at', '<', now()->subDay())->delete());
            $this->daily('pap:daily:failed', fn () => DB::table('failed_jobs')
                ->where('failed_at', '<', now()->subDays(30))->delete());

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
            try {
                $job();
            } catch (Throwable $e) {
                report($e); // úklid nesmí shodit cron
            }
        }
    }
}
