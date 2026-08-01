<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrustNgrokProxy;
use App\Providers;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Providers\AppServiceProvider::class,
        Providers\AppUrlProvider::class,
        Providers\DevCommandsServiceProvider::class,
        BroadcastServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php'
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Swap the framework's TrustProxies middleware for our subclass, which
        // also trusts ngrok's X-Forwarded-Proto when the Host is an ngrok
        // domain. This keeps the https:// scheme (and so https:// URLs) when
        // reached through the tunnel — see App\Providers\AppUrlProvider.
        // Replacing the stack entry is more explicit than binding the framework
        // class to a subclass in the container, though that would work too:
        // class-string middleware is resolved via the container.
        $middleware->remove(TrustProxies::class);
        $middleware->append(TrustNgrokProxy::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
