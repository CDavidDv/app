<?php

namespace App\Providers;

use App\Support\Access;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $by = fn (Request $r) => (string) ($r->session()->get(Access::PARTICIPANT) ?: $r->ip());

        // Intentos con tokens: evita adivinar enlaces por fuerza bruta.
        RateLimiter::for('links', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('participate', fn (Request $r) => Limit::perMinute(90)->by($by($r)));
        // Un video de 800 MB son 160 partes: margen suficiente con reintentos.
        RateLimiter::for('chunks', fn (Request $r) => Limit::perMinute(400)->by($by($r)));
        RateLimiter::for('media', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));
    }
}
