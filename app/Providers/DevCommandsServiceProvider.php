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

        // Pin the dev server to a known port so the ngrok tunnel can target it:
        // without an explicit --port, "serve" silently bumps the port upward
        // when 8000 is taken, and ngrok would point at the wrong socket.
        // SERVER_PORT (falling back to 8000) is shared with scripts/dev-ngrok.sh.
        //
        // --host must be 127.0.0.1, not localhost: PHP's built-in server binds
        // "localhost" to the IPv6 ::1 loopback on macOS, which ngrok never
        // reaches (it connects to 127.0.0.1), so the tunnel would return a
        // connection-refused "bad gateway" page.
        DevCommands::artisan('serve --host=127.0.0.1 --port=${SERVER_PORT:-8000}', 'server');

        DevCommands::register(base_path('scripts/dev-ngrok.sh'), 'ngrok');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
