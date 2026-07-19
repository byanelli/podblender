<?php

namespace App\Articles;

use App\Articles\Contracts\Fetcher as FetcherContract;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use RuntimeException;

/**
 * Retrieves the raw HTML for an article, either straight from the publisher or,
 * for gated pages, from the newest archive.is snapshot routed through the
 * residential proxy.
 */
readonly class Fetcher implements FetcherContract
{
    public function __construct(
        private Factory $http,
        private Config $config,
        private ResidentialProxyConfig $residentialProxy,
    ) {}

    public function fetchDirect(string $url): string
    {
        return $this->http
            ->withHeaders(['User-Agent' => $this->config->get('articles.user_agent')])
            ->timeout(30)
            ->get($url)
            ->throw()
            ->body();
    }

    public function fetchFromArchive(string $url): string
    {
        $archiveUrl = $this->archiveUrl($url);

        // archive.is is adversarial (Cloudflare, rate limits), so the request
        // goes out through a residential exit address. CONNECT tunnel, so TLS
        // stays verifiable end to end.
        $response = $this->http
            ->withOptions(['proxy' => $this->residentialProxy->getUrlForDownload()])
            ->withHeaders(['User-Agent' => $this->config->get('articles.user_agent')])
            ->timeout(60)
            ->get($archiveUrl);

        $body = $response->body();

        if ($response->failed() || trim($body) === '') {
            throw new RuntimeException("No archive snapshot found for: $url");
        }

        return $body;
    }

    private function archiveUrl(string $url): string
    {
        $base = rtrim((string) $this->config->get('articles.archive_base_url'), '/');

        return "$base/newest/$url";
    }
}
