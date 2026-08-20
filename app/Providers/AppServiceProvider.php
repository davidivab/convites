<?php

namespace App\Providers;

use App\Models\Iniciativa;
use App\Policies\IniciativaPolicy;
use Illuminate\Support\Facades\Gate;
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

        // Avances de convite (P54, D-D): addressing distinct from the
        // legacy `{iniciativa}` (id-bound) routes — zero behavior change
        // to those ~10 existing routes.
        Route::bind('iniciativa_uuid', fn (string $value) => Iniciativa::query()
            ->where('uuid', $value)
            ->firstOrFail());
    }
}
