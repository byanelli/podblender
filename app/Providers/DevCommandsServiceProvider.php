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

        // Pin the dev server to a known IPv4 port so the ngrok tunnel (from the
        // byanelli/ngrok-integration package) can target it. This has to live in
        // the app rather than the package: Laravel gives app-registered dev
        // commands priority over vendor ones, and without an explicit --host the
        // built-in server binds "localhost" to the IPv6 ::1 loopback on macOS,
        // which ngrok never reaches.
        //
        // SERVER_PORT (falling back to 8000) is shared with the package's
        // tunnel, which reads the same env var.
        //
        // A custom router (server.php) replaces plain "php artisan serve" so
        // /storage/* audio answers HTTP Range requests — podcast apps can't
        // stream episodes without them (they download the file instead).
        DevCommands::register(
            'php -S 127.0.0.1:${SERVER_PORT:-8000} -t public server.php',
            'server',
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
