<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Náhrada SSH na sdíleném hostingu:
 *  - /_cron/{token}           … cron hostingu volá každou minutu (pokud neumí PHP CLI)
 *  - POST /_maint/{action}     … migrace a údržba po nasazení (hlavička X-Maintenance-Token)
 *  - GET  /_health             … stav DB, úložiště a posledního běhu cronu
 */
class SystemController extends Controller
{
    private const ACTIONS = ['migrate', 'migrate-status', 'seed', 'optimize', 'optimize-clear', 'setup', 'tick', 'log'];

    public function cron(string $token): JsonResponse
    {
        $expected = (string) config('pap.cron_token');
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        @set_time_limit(90);
        ignore_user_abort(true);
        $code = Artisan::call('pap:tick');

        return response()->json(['ok' => $code === 0, 'output' => trim(Artisan::output())]);
    }

    public function maintenance(Request $request, string $action): JsonResponse
    {
        $expected = (string) config('pap.maintenance_token');
        $given = (string) $request->header('X-Maintenance-Token');
        abort_if($expected === '' || $given === '' || ! hash_equals($expected, $given), 404);
        abort_unless(in_array($action, self::ACTIONS, true), 404);

        @set_time_limit(300);

        if ($action === 'log') {
            return response()->json(['ok' => true, 'action' => 'log', 'output' => $this->logTail()]);
        }

        try {
            $code = match ($action) {
                'migrate' => Artisan::call('migrate', ['--force' => true]),
                'migrate-status' => Artisan::call('migrate:status'),
                'seed' => Artisan::call('db:seed', ['--force' => true]),
                'optimize' => Artisan::call('optimize'),
                'optimize-clear' => Artisan::call('optimize:clear'),
                'setup' => $this->setup(),
                'tick' => Artisan::call('pap:tick'),
            };
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'action' => $action, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => $code === 0, 'action' => $action, 'output' => trim(Artisan::output())]);
    }

    public function health(): JsonResponse
    {
        $checks = ['app' => true];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            $checks['database'] = false;
        }

        try {
            Storage::disk('local')->put('.health', now()->toIso8601String());
            $checks['storage'] = true;
        } catch (Throwable) {
            $checks['storage'] = false;
        }

        $last = Cache::get('pap:tick:last');
        $checks['cron_last_run'] = $last;
        $checks['cron_ok'] = $last !== null && now()->diffInMinutes($last, true) < 5;

        $ok = $checks['database'] && $checks['storage'];

        return response()->json($checks, $ok ? 200 : 503);
    }

    /** Posledních ~80 řádků logu (bez SSH není jak se k logu dostat) */
    private function logTail(): string
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        if (! $files) {
            return '(žádný log)';
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $lines = @file($files[0], FILE_IGNORE_NEW_LINES) ?: [];
        // jen hlavní řádky chyb, bez stack trace
        $lines = array_values(array_filter($lines, fn ($l) => preg_match('/^\[\d{4}-\d{2}-\d{2}/', $l)));

        return basename($files[0])."\n".implode("\n", array_map(fn ($l) => mb_substr($l, 0, 400), array_slice($lines, -80)));
    }

    /** Vytvoří adresáře, které FTP deploy nenahrává (storage je z nasazení vyloučené) */
    private function setup(): int
    {
        foreach ([
            storage_path('app/private/attachments'),
            storage_path('app/public'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $dir) {
            File::ensureDirectoryExists($dir, 0775);
        }

        return 0;
    }
}
