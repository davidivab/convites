<?php

namespace App\Providers;

use App\Models\Iniciativa;
use App\Policies\IniciativaPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Iniciativa::class, IniciativaPolicy::class);

        // Login/register: en local/testing sin tope (demo + BFF comparten IP).
        // En prod: 30/min por IP (el antiguo 5/min se llenaba al probar demos).
        RateLimiter::for('login', function (Request $request) {
            if (app()->environment('local', 'testing')) {
                return Limit::none();
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            if (app()->environment('local', 'testing')) {
                return Limit::none();
            }

            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('bot', function (Request $request) {
            if (app()->environment('local', 'testing')) {
                return Limit::none();
            }

            $token = (string) ($request->bearerToken() ?? '');

            return Limit::perMinute(60)->by($token !== '' ? sha1($token) : $request->ip());
        });

        // Avances de convite (P54, D-D): addressing distinct from the
        // legacy `{iniciativa}` (id-bound) routes — zero behavior change
        // to those ~10 existing routes.
        Route::bind('iniciativa_uuid', fn (string $value) => Iniciativa::query()
            ->where('uuid', $value)
            ->firstOrFail());
    }
}
