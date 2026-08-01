<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Providers;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Providers\AppServiceProvider::class,
        Providers\DevCommandsServiceProvider::class,
        BroadcastServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php'
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The byanelli/ngrok-integration package replaces the framework's
        // TrustProxies middleware with its own ngrok-aware subclass via a
        // container binding, so no bootstrap wiring is needed here.

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
