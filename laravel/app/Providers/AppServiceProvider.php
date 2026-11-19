<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fails closed until the Python network service gateway is implemented and bound (Phase 6).
        $this->app->bind(
            \App\Services\Network\NetworkGateway::class,
            \App\Services\Network\UnavailableGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
