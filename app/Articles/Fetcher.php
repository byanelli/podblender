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
