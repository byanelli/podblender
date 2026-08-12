<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class DevCommandsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        DevCommands::artisan('reverb:start');

        // Subscriptions only stay current if something dispatches
        // UpdateAllSubscriptions on a schedule, which in production is cron.
        // Without this, a subscription created in dev is filled in once and
        // then never updated again, so the headline feature looks broken to
        // anyone who only ever runs "php artisan dev".
        DevCommands::artisan('schedule:work', 'scheduler');

        // The dev "server" process needs no config here: the
        // byanelli/ngrok-integration package binds the framework's ServeCommand
        // to its own subclass, which serves on IPv4 127.0.0.1 (reachable by the
        // ngrok agent) and answers /storage/* audio with HTTP Range support.
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
