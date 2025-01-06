<?php

namespace App\Proxies;

use App\Proxies\Contracts\VpnProxyConfig;
use Illuminate\Contracts\Config\Repository;

class IpVanishProxyConfig implements VpnProxyConfig
{
    public function __construct(private Repository $config) {}

    public function getProtocol(): string {
        return 'socks5';
    }

    public function getHost(): string {
        return collect([
            'mel.socks.ipvanish.com',
            'tor.socks.ipvanish.com',
            'lin.socks.ipvanish.com',
            'ams.socks.ipvanish.com',
            'waw.socks.ipvanish.com',
            'sin.socks.ipvanish.com',
            'mad.socks.ipvanish.com',
            'lon.socks.ipvanish.com',
            'iad.socks.ipvanish.com',
            'atl.socks.ipvanish.com',
            'chi.socks.ipvanish.com',
            'cvg.socks.ipvanish.com',
            'dal.socks.ipvanish.com',
            'lax.socks.ipvanish.com',
            'mia.socks.ipvanish.com',
            'nyc.socks.ipvanish.com',
            'phx.socks.ipvanish.com',
            'sjc.socks.ipvanish.com',
        ])->random();
    }

    public function getPort(): int {
        return 1080;
    }

    public function getUser(): string {
        return $this->config->get('services.ip_vanish.user');
    }

    public function getPassword(): string {
        return $this->config->get('services.ip_vanish.password');
    }
}
