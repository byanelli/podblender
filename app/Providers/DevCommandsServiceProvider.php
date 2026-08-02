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
