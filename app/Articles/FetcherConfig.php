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

    public bool $scrapflySnapshotRenderJs;

    public bool $scrapflyListingRenderJs;

    public function __construct(Config $config)
    {
        $this->userAgent = (string) $config->get('articles.user_agent');
        $this->waybackBaseUrl = rtrim((string) $config->get('articles.wayback_base_url'), '/');
        $this->archiveBaseUrl = rtrim((string) $config->get('articles.archive_base_url'), '/');
        $this->scrapflySnapshotRenderJs = (bool) $config->get('articles.scrapfly_snapshot_render_js', true);
        $this->scrapflyListingRenderJs = (bool) $config->get('articles.scrapfly_listing_render_js', false);
    }
}
