<?php

namespace App\Apis\Resend;

use Illuminate\Contracts\Config\Repository as Config;

readonly class ClientConfig
{
    public string $apiKey;

    public function __construct(Config $config)
    {
        $this->apiKey = (string) $config->get('services.resend.key');
    }
}
