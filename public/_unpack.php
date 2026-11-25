<?php

/**
 * Rozbalení nasazení na serveru bez SSH.
 *
 * GitHub Action nahraje přes FTP jeden soubor _deploy/release.zip a zavolá
 * POST /_unpack.php s hlavičkou X-Maintenance-Token. Skript běží bez Laravelu
 * (při prvním nasazení ještě není vendor/), token čte přímo z .env.
 * .env ani obsah storage/ se nepřepisují.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function finish(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$base = dirname(__DIR__);

// --- token z .env ---
$token = '';
$env = @file_get_contents($base.'/.env');
if ($env !== false && preg_match('/^PAP_MAINTENANCE_TOKEN=(.*)$/m', $env, $m)) {
    $token = trim(trim($m[1]), "\"'");
}
$given = (string) ($_SERVER['HTTP_X_MAINTENANCE_TOKEN'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $token === '' || $given === '' || ! hash_equals($token, $given)) {
    finish(404, ['ok' => false]);
}

if (! class_exists(ZipArchive::class)) {
    finish(500, ['ok' => false, 'error' => 'Na serveru chybí PHP rozšíření zip (ZipArchive).']);
}

$zipPath = $base.'/_deploy/release.zip';
if (! is_file($zipPath)) {
    finish(404, ['ok' => false, 'error' => 'Nenalezen _deploy/release.zip']);
}

@set_time_limit(300);
ignore_user_abort(true);

$zip = new ZipArchive;
if ($zip->open($zipPath) !== true) {
    finish(500, ['ok' => false, 'error' => 'Zip nejde otevřít (nedokončené nahrání?)']);
}

$skipped = 0;
$written = 0;

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    $name = ltrim(str_replace('\\', '/', $name), '/');

    // ochrana proti cestám mimo aplikaci
    if ($name === '' || str_contains($name, '../')) {
        $skipped++;

        continue;
    }

    // nepřepisovat konfiguraci a data serveru
    if ($name === '.env' || (str_starts_with($name, 'storage/') && ! str_ends_with($name, '.gitignore'))) {
        $skipped++;

        continue;
    }

    $target = $base.'/'.$name;

    if (str_ends_with($name, '/')) {
        if (! is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        continue;
    }

    $dir = dirname($target);
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $stream = $zip->getStream($zip->getNameIndex($i));
    if ($stream === false) {
        $zip->close();
        finish(500, ['ok' => false, 'error' => 'Nelze číst '.$name]);
    }

    $tmp = $target.'.tmp-unpack';
    $out = fopen($tmp, 'wb');
    if ($out === false) {
        fclose($stream);
        $zip->close();
        finish(500, ['ok' => false, 'error' => 'Nelze zapsat '.$name]);
    }
    stream_copy_to_stream($stream, $out);
    fclose($out);
    fclose($stream);
    rename($tmp, $target);
    $written++;
}

$zip->close();
@unlink($zipPath);

// vyčistit cache konfigurace a rout z předchozí verze
foreach (glob($base.'/bootstrap/cache/{config,routes-*,events}.php', GLOB_BRACE) ?: [] as $cached) {
    @unlink($cached);
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
}

finish(200, ['ok' => true, 'written' => $written, 'skipped' => $skipped]);
