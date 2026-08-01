<?php

namespace App\Providers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

/**
 * Keeps config('app.url') consistent with how the app is actually being
 * reached. Locally through ngrok the request host is the tunnel's public
 * domain, not APP_URL's localhost, so any code that reads app.url (rather than
 * deriving it from the request) would otherwise mint localhost links. Trivially
 * a no-op for ordinary localhost traffic.
 */
class AppUrlProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /** @var Request $request */
        $request = $this->app->make('request');

        if ($this->app->isLocal() && $request->getHost() !== '') {
            $localHosts = ['localhost', '127.0.0.1', '[::1]'];

            if (! in_array($request->getHost(), $localHosts, true)) {
                $this->app->make(Config::class)->set(
                    'app.url',
                    $request->getScheme().'://'.$request->getHost(),
                );
            }
        }
    }
}
