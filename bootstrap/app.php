<?php

use App\Providers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Providers\AppServiceProvider::class,
        \Illuminate\Broadcasting\BroadcastServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
