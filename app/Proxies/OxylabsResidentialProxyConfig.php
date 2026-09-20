<?php

namespace App\Proxies;

use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Oxylabs' residential pool, which routes requests through home connections. YouTube blocks addresses that belong to
 * hosting companies, which includes every commercial VPN, far more readily than addresses that belong to home ISPs.
 *
 * Without session parameters the pool uses a different exit IP for every request, which breaks downloads: see
 * ProxyConfig::getUrlForDownload(). The session parameters below keep one address for one download.
 *
 * @see https://developers.oxylabs.io/products/proxies/residential-proxies/session-control
 */
class OxylabsResidentialProxyConfig implements ResidentialProxyConfig
{
    private const string HOST = 'pr.oxylabs.io';

    private const int PORT = 7777;

    /**
     * How long a session keeps its address, in minutes. Oxylabs' default is 10 and the maximum is 1440. A lecture is
     * around 96MB and downloads in under a minute at measured throughput, so this is longer than needed, which costs
     * nothing. An address that changes during a download fails with a 403.
     */
    private const int SESSION_MINUTES = 60;

    public function __construct(private readonly Repository $config) {}

    public function isConfigured(): bool
    {
        return ! is_null($this->credentials());
    }

    public function getUrlForDownload(): string
    {
        $credentials = $this->credentials();

        // Callers should check isConfigured() first.
        if (is_null($credentials)) {
            throw new RuntimeException(
                'No Oxylabs credentials are configured; set OXYLABS_USERNAME and OXYLABS_PASSWORD to use the '
                .'residential proxy.'
            );
        }

        [$user, $password] = $credentials;

        // Oxylabs reads its parameters from the username.
        $username = collect([
            'customer' => $user,

            // Downloads are refused from some countries, and an address near the content is faster.
            'cc'       => $this->config->get('services.oxylabs.residential.country'),

            // A new session per call gives each download attempt its own address.
            'sessid'   => Str::random(16),

            'sesstime' => self::SESSION_MINUTES,
        ])
            ->map(fn (string|int $value, string $key) => "$key-$value")
            ->join('-');

        // Oxylabs' generated passwords often contain characters that are reserved in a URL.
        return sprintf(
            'http://%s:%s@%s:%d',
            rawurlencode($username),
            rawurlencode($password),
            self::HOST,
            self::PORT,
        );
    }

    /**
     * The configured user and password, or null unless both are set. A blank value counts as unset, since a .env
     * copied from the example has "OXYLABS_USERNAME=" with no value.
     *
     * @return array{string, string}|null
     */
    private function credentials(): ?array
    {
        $user = $this->config->get('services.oxylabs.residential.user');
        $password = $this->config->get('services.oxylabs.residential.password');

        if (! is_string($user) || ! is_string($password)) {
            return null;
        }

        if (trim($user) === '' || trim($password) === '') {
            return null;
        }

        return [$user, $password];
    }

    public function requiresInsecureTls(): bool
    {
        // Oxylabs tunnels with CONNECT, so TLS stays end-to-end with YouTube and can be verified.
        return false;
    }
}
