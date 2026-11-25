<?php

use Illuminate\Support\Facades\Route;

// Webový portál (dispečer, admin, zákazník) přibude jako SPA nad /api/v1
Route::get('/', fn () => response()->json(['app' => 'PAP Service', 'api' => url('/api/v1')]));
