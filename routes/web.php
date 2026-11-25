<?php

use Illuminate\Support\Facades\Route;

// Rozcestník: dispečink (počítač) a aplikace technika (telefon)
Route::get('/', fn () => response()->view('home'));

Route::redirect('/technik', '/technik/');
Route::redirect('/dispecink', '/dispecink/');
Route::redirect('/zakaznik', '/zakaznik/');

// Apache servíruje /technik/, /dispecink/ a /zakaznik/ přímo ze složek v public/ (DirectoryIndex).
// Tyhle routy jsou jen pojistka pro servery, které adresář nepředají (např. php artisan serve).
Route::get('/technik/', fn () => response()->file(public_path('technik/index.html'), ['Cache-Control' => 'no-cache']));
Route::get('/dispecink/', fn () => response()->file(public_path('dispecink/index.html'), ['Cache-Control' => 'no-cache']));
Route::get('/zakaznik/', fn () => response()->file(public_path('zakaznik/index.html'), ['Cache-Control' => 'no-cache']));
