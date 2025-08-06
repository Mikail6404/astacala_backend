<?php

namespace App\Providers;

use App\Services\CrossPlatformNotificationService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CrossPlatformNotificationService::class, function ($app) {
            return new CrossPlatformNotificationService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
