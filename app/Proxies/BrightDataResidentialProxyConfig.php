<?php

namespace App\Proxies;

use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;

class BrightDataResidentialProxyConfig implements ResidentialProxyConfig
{
    public function __construct(private Repository $config) {}

    public function getProtocol(): string {
        return 'https';
    }

    public function getHost(): string {
        return 'brd.superproxy.io';
    }

    public function getPort(): int {
        return 22225;
    }

    public function getUser(): string {
        return $this->config->get('services.bright_data.residential.user');
    }

    public function getPassword(): string {
        return $this->config->get('services.bright_data.residential.password');
    }
}
