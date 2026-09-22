<?php

namespace App\Articles;

use App\Apis\Scraping\Contracts\Scraper;
use App\Apis\Scraping\ScrapeResult;
use App\Apis\Scraping\ScraperException;
use App\Articles\Contracts\Fetcher as FetcherContract;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Retrieves the raw HTML for an article. Open pages use a free, direct GET;
 * paywalled ones come from an archive.
 *
 * archive.is is behind Cloudflare and serves an interactive CAPTCHA to a plain
 * HTTP client, so its snapshots are fetched through the configured Scraper,
 * which costs money per request.
 */
readonly class Fetcher implements FetcherContract
{
    public function __construct(
        private Factory $http,
        private FetcherConfig $config,
        private Scraper $scraper,
        private ResidentialProxyConfig $residentialProxy,
    ) {}

    public function fetchDirect(string $url): string
    {
        return $this->http
            ->withHeaders(['User-Agent' => $this->config->userAgent])
            ->timeout(30)
            ->get($url)
            ->throw()
            ->body();
    }

    /**
     * The middle tier, tried before archive.is. web.archive.org has no
     * Cloudflare CAPTCHA, so it needs no scraper. archive.org does rate-limit
     * and block the datacenter IP production runs from, so both requests go
     * through the residential proxy, which costs bandwidth but no scraper
     * fees:
     *   1. the availability API returns the closest snapshot's timestamp; and
     *   2. that snapshot is fetched in raw "id_" form, without Wayback's
     *      injected toolbar, so the Extractor gets the original markup.
     *
     * Any failure becomes WaybackSnapshotNotFoundException so the Reader can
     * continue to archive.is.
     */
    public function fetchFromWayback(string $url): string
    {
        $timestamp = $this->waybackClosestTimestamp($url);

        if ($timestamp === null) {
            throw new WaybackSnapshotNotFoundException("No Wayback snapshot exists for: $url");
        }

        try {
            return $this->waybackRequest()
                ->get($this->waybackSnapshotUrl($timestamp, $url))
                ->throw()
                ->body();
        } catch (\Throwable $e) {
            // Report a failed snapshot fetch as a missing snapshot so the Reader
            // continues to archive.is.
            throw new WaybackSnapshotNotFoundException('Wayback snapshot fetch failed: '.$e->getMessage());
        }
    }

    /**
     * Return the closest snapshot's timestamp from the availability API, or null
     * when there is none. An API error also returns null, so the read continues
     * to archive.is.
     */
    private function waybackClosestTimestamp(string $url): ?string
    {
        try {
            $response = $this->waybackRequest()
                ->get($this->waybackAvailabilityUrl(), ['url' => $url])
                ->throw();
        } catch (\Throwable) {
            return null;
        }

        $closest = $response->json('archived_snapshots.closest');

        if (! is_array($closest) || ($closest['available'] ?? null) !== true) {
            return null;
        }

        $timestamp = $closest['timestamp'] ?? null;

        return (is_string($timestamp) && $timestamp !== '') ? $timestamp : null;
    }

    /**
     * A request to archive.org through the residential proxy, because
     * archive.org blocks datacenter IPs.
     */
    private function waybackRequest(): PendingRequest
    {
        return $this->http
            ->withOptions(['proxy' => $this->residentialProxy->getUrlForDownload()])
            ->withHeaders(['User-Agent' => $this->config->userAgent])
            ->timeout(30);
    }

    private function waybackAvailabilityUrl(): string
    {
        return $this->config->waybackBaseUrl.'/wayback/available';
    }

    /**
     * Build the raw snapshot URL. The availability API is on archive.org but
     * snapshots are served from web.archive.org, so "web." is prefixed to the
     * host. The "id_" after the timestamp suppresses Wayback's injected
     * navigation toolbar.
     */
    private function waybackSnapshotUrl(string $timestamp, string $url): string
    {
        $base = $this->config->waybackBaseUrl;

        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: 'archive.org');
        $webHost = str_starts_with($host, 'web.') ? $host : 'web.'.$host;

        return "$scheme://$webHost/web/{$timestamp}id_/$url";
    }

    public function fetchFromArchive(string $url): string
    {
        // archive.today redirects /newest/{url} to the newest snapshot, and
        // answers 404 when it has none.
        $result = $this->scrape(
            "{$this->config->archiveBaseUrl}/newest/{$url}",
            $this->config->archiveRenderJs,
        );

        if ($result->statusCode === 404) {
            throw new ArchiveSnapshotNotFoundException("No archive snapshot exists for: $url");
        }

        // Any other error status can be retried. It doesn't show that the
        // snapshot is missing.
        if ($result->statusCode >= 400) {
            throw new ArchiveBlockedException("Archive fetch blocked (HTTP {$result->statusCode}) for: $url");
        }

        return $result->content;
    }

    private function scrape(string $url, bool $renderJs): ScrapeResult
    {
        try {
            return $this->scraper->scrape($url, $renderJs);
        } catch (ScraperException $e) {
            // A scraper error is reported as blocked, which the caller can
            // retry later, unlike a missing snapshot.
            throw new ArchiveBlockedException('Archive fetch was blocked: '.$e->getMessage());
        }
    }
}
