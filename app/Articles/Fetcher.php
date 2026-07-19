<?php

namespace App\Articles;

use App\Apis\Scrapfly\Contracts\Client as Scrapfly;
use App\Apis\Scrapfly\ScrapflyException;
use App\Apis\Scrapfly\ScrapflyResult;
use App\Articles\Contracts\Fetcher as FetcherContract;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;

/**
 * Retrieves the raw HTML for an article. A free, direct Guzzle GET for open
 * pages; a two-step Scrapfly flow for gated ones.
 *
 * archive.is sits behind Cloudflare and serves an interactive CAPTCHA to a raw
 * HTTP client, so the archive path goes through Scrapfly's ASP (the only thing
 * proven to clear it). Resolving a URL to its snapshot HTML is two Scrapfly
 * calls, and BOTH SPEND CREDITS:
 *   1. the snapshot listing ({base}/{url}, render_js off, ~25 credits), which we
 *      parse for the newest snapshot; then
 *   2. that snapshot ({snapshot-url}, render_js on, ~30 credits) — the archived
 *      article HTML the Extractor consumes.
 */
readonly class Fetcher implements FetcherContract
{
    public function __construct(
        private Factory $http,
        private Config $config,
        private Scrapfly $scrapfly,
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

    /**
     * The free middle tier. web.archive.org is NOT Cloudflare-fronted, so this
     * is a plain Guzzle GET — no Scrapfly, no credits. Two hops:
     *   1. the availability API tells us the closest snapshot's timestamp; and
     *   2. we GET that snapshot in raw "id_" form (Wayback's injected toolbar
     *      stripped) so the Extractor sees the original archived markup.
     *
     * A miss or any hiccup becomes WaybackSnapshotNotFoundException so the Reader
     * can fall through to archive.is — a Wayback stumble must never fail a read.
     */
    public function fetchFromWayback(string $url): string
    {
        $timestamp = $this->waybackClosestTimestamp($url);

        if ($timestamp === null) {
            throw new WaybackSnapshotNotFoundException("No Wayback snapshot exists for: $url");
        }

        try {
            return $this->http
                ->withHeaders(['User-Agent' => $this->config->get('articles.user_agent')])
                ->timeout(30)
                ->get($this->waybackSnapshotUrl($timestamp, $url))
                ->throw()
                ->body();
        } catch (\Throwable $e) {
            // Wayback is best-effort; archive.is is the backstop. A snapshot-fetch
            // error must not escalate — degrade it to a "no snapshot" miss.
            throw new WaybackSnapshotNotFoundException('Wayback snapshot fetch failed: '.$e->getMessage());
        }
    }

    /**
     * Hit the availability API and return the closest snapshot's timestamp, or
     * null when there is none. An API-level hiccup is treated as "no snapshot"
     * too, so the whole read degrades to the archive.is backstop rather than
     * failing on a free tier.
     */
    private function waybackClosestTimestamp(string $url): ?string
    {
        try {
            $response = $this->http
                ->withHeaders(['User-Agent' => $this->config->get('articles.user_agent')])
                ->timeout(30)
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

    private function waybackAvailabilityUrl(): string
    {
        return rtrim((string) $this->config->get('articles.wayback_base_url'), '/').'/wayback/available';
    }

    /**
     * Build the raw snapshot URL. Availability answers on the bare host
     * (archive.org), but snapshots are served from web.archive.org, so we derive
     * the web host by prefixing "web." The "id_" after the timestamp is what
     * suppresses Wayback's injected navigation toolbar.
     */
    private function waybackSnapshotUrl(string $timestamp, string $url): string
    {
        $base = rtrim((string) $this->config->get('articles.wayback_base_url'), '/');

        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $host = (string) (parse_url($base, PHP_URL_HOST) ?: 'archive.org');
        $webHost = str_starts_with($host, 'web.') ? $host : 'web.'.$host;

        return "$scheme://$webHost/web/{$timestamp}id_/$url";
    }

    public function fetchFromArchive(string $url): string
    {
        $snapshotUrl = $this->newestSnapshotUrl($this->fetchListing($url));

        // The snapshot page renders JS: the archived article body is what the
        // Extractor needs. This is the ~30-credit half of the lookup.
        return $this->scrape(
            $snapshotUrl,
            (bool) $this->config->get('articles.scrapfly_snapshot_render_js', true),
        )->content;
    }

    /**
     * Step 1: the static snapshot listing. Cheaper (no JS render). A Scrapfly
     * failure here is a BLOCK, not an absence — the listing may exist, we just
     * couldn't retrieve it.
     */
    private function fetchListing(string $url): string
    {
        $result = $this->scrape(
            $this->listingUrl($url),
            (bool) $this->config->get('articles.scrapfly_listing_render_js', false),
        );

        // archive.is answering with a blocking status is a retryable block, not
        // proof the snapshot is missing.
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
            // Blocked, not absent: Scrapfly erred or reported failure. Distinct
            // from an empty listing so the caller can retry later.
            throw new ArchiveBlockedException('Archive fetch was blocked: '.$e->getMessage());
        }
    }

    /**
     * Parse the newest snapshot URL out of an archive.today listing.
     *
     * The listing renders each snapshot as an anchor to https://archive.<tld>/
     * <5-char-code> whose text carries a date. A naive regex over the raw page
     * also matches archive.is/https, /loadi, /searc (truncations of unrelated
     * links), so we walk the DOM, keep only anchors whose href is EXACTLY a
     * 5-char snapshot code AND that carry a parseable date, and pick the latest.
     *
     * @throws ArchiveSnapshotNotFoundException when the listing holds no snapshot rows
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
        $base = rtrim((string) $this->config->get('articles.archive_base_url'), '/');

        return "$base/$url";
    }
}
