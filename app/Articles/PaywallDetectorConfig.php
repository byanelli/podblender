<?php

namespace App\Articles;

use Illuminate\Contracts\Config\Repository as Config;

readonly class PaywallDetectorConfig
{
    public int $minBodyLength;

    /** @var list<string> */
    public array $markers;

    /** @var list<string> */
    public array $selectors;

    public function __construct(Config $config)
    {
        $this->minBodyLength = (int) $config->get('articles.min_body_length');
        $this->markers = $config->get('articles.paywall_markers', []);
        $this->selectors = $config->get('articles.paywall_selectors', []);
    }
}
