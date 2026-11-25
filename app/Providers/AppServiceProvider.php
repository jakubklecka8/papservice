<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // V DB se ukládá 'ticket' / 'visit' místo názvů tříd
        Relation::enforceMorphMap([
            'ticket' => Ticket::class,
            'visit' => Visit::class,
            'user' => User::class, // Sanctum tokeny
        ]);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(6)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}
