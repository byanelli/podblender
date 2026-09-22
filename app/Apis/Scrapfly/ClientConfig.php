<?php

namespace App\Apis\Scrapfly;

use Illuminate\Contracts\Config\Repository as Config;

readonly class ClientConfig
{
    public string $apiKey;

    /** Seconds. archive.is takes 50 to 75 seconds through Scrapfly. */
    public int $timeout;

    /** The country Scrapfly requests the page from, as a two-letter code. */
    public string $country;

    public function __construct(Config $config)
    {
        $this->apiKey = (string) $config->get('services.scrapfly.key');
        $this->timeout = (int) $config->get('articles.scrapfly_timeout', 180);
        $this->country = (string) $config->get('articles.scrapfly_country', 'us');
    }
}
