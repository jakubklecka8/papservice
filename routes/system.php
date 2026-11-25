<?php

use App\Http\Controllers\System\SystemController;
use Illuminate\Support\Facades\Route;

// Bez session, CSRF a throttle – throttle potřebuje tabulku cache, která
// před první migrací neexistuje. Cron a údržba jsou chráněné tokenem.
Route::get('_cron/{token}', [SystemController::class, 'cron']);
Route::post('_maint/{action}', [SystemController::class, 'maintenance']);
Route::get('_health', [SystemController::class, 'health']);
