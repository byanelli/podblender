<?php

namespace App\Articles;

use App\Apis\Scrapfly\Contracts\Client as Scrapfly;
use App\Apis\Scrapfly\ScrapflyException;
use App\Apis\Scrapfly\ScrapflyResult;
use App\Articles\Contracts\Fetcher as FetcherContract;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Retrieves the raw HTML for an article. Open pages use a free, direct GET;
 * paywalled ones use a two-step Scrapfly flow.
 *
 * archive.is is behind Cloudflare and serves an interactive CAPTCHA to a plain
 * HTTP client, so the archive path goes through Scrapfly's ASP, the only method
 * found to pass it. Resolving a URL to its snapshot HTML takes two Scrapfly
 * calls, and both spend credits:
 *   1. the snapshot listing ({base}/{url}, render_js off, ~25 credits), parsed
 *      for the newest snapshot; then
 *   2. that snapshot ({snapshot-url}, render_js on, ~30 credits), the archived
 *      article HTML passed to the Extractor.
 */
readonly class Fetcher implements FetcherContract
{
    public function __construct(
        private Factory $http,
        private FetcherConfig $config,
        private Scrapfly $scrapfly,
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
     * Cloudflare CAPTCHA, so it needs no Scrapfly. archive.org does rate-limit
     * and block the datacenter IP production runs from, so both requests go
     * through the residential proxy, which costs bandwidth but no Scrapfly
     * credits:
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
        $snapshotUrl = $this->newestSnapshotUrl($this->fetchListing($url));

        // The ~30-credit call. render_js is on by default.
        return $this->scrape(
            $snapshotUrl,
            $this->config->scrapflySnapshotRenderJs,
        )->content;
    }

    /**
     * Step 1: the static snapshot listing, which is cheaper because it needs no
     * JS render. A Scrapfly failure here means blocked: the listing may exist
     * but couldn't be retrieved.
     */
    private function fetchListing(string $url): string
    {
        $result = $this->scrape(
            $this->listingUrl($url),
            $this->config->scrapflyListingRenderJs,
        );

        // An error status from archive.is can be retried. It doesn't show that
        // the snapshot is missing.
        if ($result->statusCode >= 400) {
            throw new ArchiveBlockedException(
                "Archive listing blocked (HTTP {$result->statusCode}) for: $url"
            );
        }

        return $result->content;
    }

    private function scrape(string $url, bool $renderJs): ScrapflyResult
    {
        try {
            return $this->scrapfly->scrape($url, $renderJs);
        } catch (ScrapflyException $e) {
            // A Scrapfly error is reported as blocked, which the caller can
            // retry later, unlike an empty listing.
            throw new ArchiveBlockedException('Archive fetch was blocked: '.$e->getMessage());
        }
    }

    /**
     * Parse the newest snapshot URL out of an archive.today listing.
     *
     * Each snapshot in the listing is an anchor to https://archive.<tld>/
     * <5-char-code> whose text includes a date. A regex over the raw page also
     * matches archive.is/https, /loadi, /searc (truncations of unrelated links),
     * so this queries the DOM and keeps only anchors whose href is exactly a
     * 5-char snapshot code and whose text has a parseable date, then returns
     * the latest.
     *
     * @throws ArchiveSnapshotNotFoundException when the listing has no snapshot rows
     */
    private function newestSnapshotUrl(string $html): string
    {
        if (trim($html) === '') {
            throw new ArchiveSnapshotNotFoundException('Archive listing was empty.');
        }

        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        $newestUrl = null;
        $newestAt = null;

        /** @var \DOMElement $anchor */
        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            $href = $anchor->getAttribute('href');

            if (! preg_match('~^https?://archive\.[a-z]+/[A-Za-z0-9]{5}$~', $href)) {
                continue;
            }

            if (! preg_match('~(\d{1,2} [A-Za-z]{3} \d{4}(?: \d{2}:\d{2})?)~', $anchor->textContent, $match)) {
                continue;
            }

            $snapshotAt = CarbonImmutable::parse($match[1]);

            if ($newestAt === null || $snapshotAt->greaterThan($newestAt)) {
                $newestAt = $snapshotAt;
                $newestUrl = $href;
            }
        }

        if ($newestUrl === null) {
            throw new ArchiveSnapshotNotFoundException('No archive snapshot exists for this URL.');
        }

        return $newestUrl;
    }

    private function listingUrl(string $url): string
    {
        $base = $this->config->archiveBaseUrl;

        return "$base/$url";
    }
}
