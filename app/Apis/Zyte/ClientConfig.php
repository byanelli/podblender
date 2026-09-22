<?php

namespace App\Apis\Zyte;

use Illuminate\Contracts\Config\Repository as Config;

readonly class ClientConfig
{
    public string $apiKey;

    /** Seconds. A rendered archive.is snapshot took up to 41 seconds. */
    public int $timeout;

    public function __construct(Config $config)
    {
        $this->apiKey = (string) $config->get('services.zyte.key');
        $this->timeout = (int) $config->get('articles.zyte_timeout', 120);
    }
}
