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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
