<?php

namespace App\Articles;

use Illuminate\Contracts\Config\Repository as Config;

readonly class FetcherConfig
{
    public string $userAgent;

    /** Without a trailing slash. */
    public string $waybackBaseUrl;

    /** Without a trailing slash. */
    public string $archiveBaseUrl;

    /** Whether the scraper renders an archive snapshot's JavaScript. */
    public bool $archiveRenderJs;

    public function __construct(Config $config)
    {
        $this->userAgent = (string) $config->get('articles.user_agent');
        $this->waybackBaseUrl = rtrim((string) $config->get('articles.wayback_base_url'), '/');
        $this->archiveBaseUrl = rtrim((string) $config->get('articles.archive_base_url'), '/');
        $this->archiveRenderJs = (bool) $config->get('articles.archive_render_js', true);
    }
}
