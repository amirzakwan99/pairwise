<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // This SPA uses cookies only. Disable Sanctum's optional bearer-token fallback.
        Sanctum::getAccessTokenFromRequestUsing(fn () => null);
    }
}
