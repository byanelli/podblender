<?php

namespace App\Proxies;

use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;

/**
 * Bright Data's residential pool.
 *
 * Careful: like any residential pool, this rotates its exit address on every request by default, which a YouTube
 * download can't survive — see {@see \App\Proxies\Contracts\ProxyConfig::getUrlForDownload()}. Bright Data pins an
 * address with a `-session-` parameter in the username, much as {@see OxylabsResidentialProxyConfig} does, but that
 * isn't done here because there's no live account to prove it against. This is untested against a real IP and is
 * likely to fail with a 403 that looks nothing like its actual cause.
 */
class BrightDataResidentialProxyConfig implements ResidentialProxyConfig
{
    private const string HOST = 'brd.superproxy.io';

    private const int PORT = 22225;

    public function __construct(private readonly Repository $config) {}

    public function getUrlForDownload(): string
    {
        return sprintf(
            'https://%s:%s@%s:%d',
            rawurlencode($this->config->get('services.bright_data.residential.user')),
            rawurlencode($this->config->get('services.bright_data.residential.password')),
            self::HOST,
            self::PORT,
        );
    }

    public function requiresInsecureTls(): bool
    {
        return true;
    }
}
