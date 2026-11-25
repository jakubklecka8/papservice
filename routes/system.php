<?php

use App\Http\Controllers\System\SystemController;
use Illuminate\Support\Facades\Route;

// Bez session a CSRF – volá je cron hostingu a GitHub Action
Route::get('_cron/{token}', [SystemController::class, 'cron'])->middleware('throttle:10,1');
Route::post('_maint/{action}', [SystemController::class, 'maintenance'])->middleware('throttle:20,1');
Route::get('_health', [SystemController::class, 'health'])->middleware('throttle:30,1');
