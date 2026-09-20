<?php

namespace App\Proxies;

use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DataImpulse's residential pool, an alternative to Oxylabs. YouTube blocks addresses that belong to hosting companies,
 * which includes every commercial VPN, far more readily than addresses that belong to home ISPs.
 *
 * This uses the rotating gateway on port 823 with a session parameter: requests that carry the same session id leave
 * from the same address. That meets ProxyConfig::getUrlForDownload()'s requirement of one address per download and a
 * different one next time. DataImpulse's per-port proxies don't, because the provider decides when their address
 * changes.
 *
 * @see https://docs.dataimpulse.com/proxies/parameters/session-id
 * @see https://docs.dataimpulse.com/proxies/parameters/session-interval
 */
class DataImpulseResidentialProxyConfig implements ResidentialProxyConfig
{
    private const string HOST = 'gw.dataimpulse.com';

    /**
     * The HTTP/HTTPS gateway, which takes its parameters from the username. Port 824 is the same over SOCKS5, and
     * ports 10000-20000 are the per-port proxies.
     */
    private const int PORT = 823;

    /**
     * How long a session keeps its address, in minutes.
     *
     * DataImpulse documents `sessttl` only for the per-port proxies, with a default of 30. Measured on 2026-09-18:
     * port 823 applies it together with `sessid`. A session polled every five minutes kept one exit address through
     * 55 minutes and had a new one at 60. Idle sessions were not tested.
     *
     * Longer than a download needs, which costs nothing. An address that changes during a download fails with a 403.
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
                'No DataImpulse credentials are configured; set DATAIMPULSE_USERNAME and DATAIMPULSE_PASSWORD to use '
                .'the residential proxy.'
            );
        }

        [$user, $password] = $credentials;

        /** @var array<string, string|int> $parameters */
        $parameters = [];

        // Downloads are refused from some countries, and an address near the content is faster. With no `cr`,
        // DataImpulse chooses the exit country.
        $country = $this->countryCode();

        if (! is_null($country)) {
            $parameters['cr'] = $country;
        }

        // A new session per call gives each download attempt its own address.
        $parameters['sessid'] = Str::random(16);

        $parameters['sessttl'] = self::SESSION_MINUTES;

        // DataImpulse reads its parameters from the username: the login, a double underscore, then key.value pairs
        // joined by semicolons.
        $username = $user.'__'.collect($parameters)
            ->map(fn (string|int $value, string $key) => "$key.$value")
            ->join(';');

        // DataImpulse's generated passwords often contain characters that are reserved in a URL. The semicolons
        // become %3B, and the client decodes the username before sending it.
        return sprintf(
            'http://%s:%s@%s:%d',
            rawurlencode($username),
            rawurlencode($password),
            self::HOST,
            self::PORT,
        );
    }

    /**
     * The exit country, or null if none is configured. A blank value counts as none, because DataImpulse refuses a
     * `cr` parameter with no value. Lowercased because every country code in DataImpulse's documentation is.
     */
    private function countryCode(): ?string
    {
        $country = $this->config->get('services.dataimpulse.residential.country');

        if (! is_string($country) || trim($country) === '') {
            return null;
        }

        return Str::lower(trim($country));
    }

    /**
     * The configured user and password, or null unless both are set. A blank value counts as unset, since a .env
     * copied from the example has "DATAIMPULSE_USERNAME=" with no value.
     *
     * @return array{string, string}|null
     */
    private function credentials(): ?array
    {
        $user = $this->config->get('services.dataimpulse.residential.user');
        $password = $this->config->get('services.dataimpulse.residential.password');

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
        // DataImpulse tunnels with CONNECT, so TLS stays end-to-end with YouTube and can be verified.
        return false;
    }
}
