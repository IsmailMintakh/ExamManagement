<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Super-admin (DDO) can do everything — mirrors the frontend
        // permission helper. Returning null lets every other role fall
        // through to the normal Spatie permission checks. This also makes
        // route `can:` middleware robust if a permission row is missing.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin')
                ? true
                : null;
        });
    }
}
