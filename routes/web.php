<?php

use Illuminate\Support\Facades\Route;

// Zatím jediné rozhraní je aplikace pro techniky; webový portál přibude později.
Route::redirect('/', '/technik/');
Route::redirect('/technik', '/technik/');

// Apache servíruje /technik/ přímo ze složky public/technik (DirectoryIndex).
// Tahle routa je jen pojistka pro servery, které adresář nepředají (např. php artisan serve).
Route::get('/technik/', fn () => response()->file(public_path('technik/index.html'), ['Cache-Control' => 'no-cache']));
