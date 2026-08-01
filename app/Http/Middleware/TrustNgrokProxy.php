<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Like the framework's TrustProxies, plus trust for ngrok.
 *
 * ngrok terminates HTTPS and forwards to the app over plain HTTP with
 * X-Forwarded-Proto: https etc. Without honoring those headers the app thinks
 * it's on http:// and mints http:// URLs, which browsers block as mixed content
 * on the https:// tunnel page.
 *
 * This mirrors the framework's own branch for its Forge/Vapor hosts: when the
 * request's Host is an ngrok domain we trust the proxy headers — but only from
 * the loopback, because the ngrok agent runs on this machine and dials the app
 * from 127.0.0.1/::1. A LAN client that reaches the app directly therefore
 * can't spoof the forwarded headers even if it lies about the Host.
 *
 * Registered in bootstrap/app.php in place of the framework's TrustProxies.
 */
class TrustNgrokProxy extends TrustProxies
{
    private const NGROK_SUFFIXES = ['.ngrok-free.dev', '.ngrok.app', '.ngrok.io'];

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        foreach (self::NGROK_SUFFIXES as $suffix) {
            if (str_ends_with($request->host(), $suffix)) {
                // Reuse the framework's helper, which honours the configured
                // header set and the REMOTE_ADDR special case.
                $this->setTrustedProxyIpAddressesToSpecificIps($request, ['127.0.0.1', '::1']);

                return;
            }
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
