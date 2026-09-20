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

        // Runs the scheduler in dev (cron does this in production), so
        // UpdateAllSubscriptions keeps dev subscriptions up to date.
        DevCommands::artisan('schedule:work', 'scheduler');

        // The dev "server" process needs no config here. The
        // byanelli/ngrok-integration package binds ServeCommand to a subclass
        // that serves on IPv4 127.0.0.1 (reachable by the ngrok agent) and
        // answers /storage/* audio with HTTP Range support.
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
