<?php

namespace App\Articles;

use App\Articles\Contracts\Fetcher;
use App\Articles\Contracts\Reader as ReaderContract;
use App\Concerns\FixesUrls;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use League\Uri\Uri;

/**
 * Fetches a page, extracts the article, retries through the archives when it
 * is paywalled, and caches the result. The Web platform calls this class;
 * everything else in App\Articles is an implementation detail of it.
 */
readonly class Reader implements ReaderContract
{
    use FixesUrls;

    public function __construct(
        private Fetcher $fetcher,
        private Extractor $extractor,
        private PaywallDetector $paywallDetector,
        private Cache $cache,
        #[Config('articles.cache_ttl_hours')] private int $cacheTtlHours,
        /** @var list<string> */
        #[Config('articles.hard_paywall_domains')] private array $hardPaywallDomains,
    ) {}

    public function read(string $url): Article
    {
        // $url has "www." removed and is the cache key and the input to the
        // hard-paywall-domain check. $archiveLookupUrl keeps any "www." because
        // archive.is indexes pages by their published URL (NYT is
        // www.nytimes.com), and a lookup without it finds nothing.
        $archiveLookupUrl = $this->removeUtmCodesFromUrl($this->ensureSchemeIsHttps($url));
        $url = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($url));

        return $this->cache->remember(
            "article:$url",
            now()->addHours($this->cacheTtlHours),
            fn () => $this->fetchAndExtract($url, $archiveLookupUrl),
        );
    }

    /**
     * Three fetch tiers, cheapest first:
     *
     *   1. Direct (free). Skipped for a hard-paywall domain, which never serves
     *      a usable page to a logged-out reader. Used only if not paywalled.
     *   2. Wayback (free). Its snapshot is often a capture of the paywalled
     *      page, so it is used only if the PaywallDetector passes it.
     *   3. archive.is (paid, one or two scraper requests). Its snapshots are
     *      user-submitted captures without the paywall, and it is the last
     *      tier, so its result is not checked.
     */
    private function fetchAndExtract(string $url, string $archiveLookupUrl): Article
    {
        if (! $this->isHardPaywallDomain($url)) {
            $direct = $this->extractor->extract($url, $html = $this->fetcher->fetchDirect($url));

            if (! $this->paywallDetector->isGated($html, $direct)) {
                return $direct;
            }
        }

        // Wayback indexes pages by the published URL, as archive.is does.
        $wayback = $this->tryWaybackTier($url, $archiveLookupUrl);

        if ($wayback !== null) {
            return $wayback;
        }

        return $this->extractor->extract($url, $this->fetchFromArchive($archiveLookupUrl));
    }

    /**
     * Looks up the URL on archive.is, then, if it has no snapshot and the host
     * lacks "www.", the same URL with "www." added. The second lookup is
     * another paid scraper request.
     */
    private function fetchFromArchive(string $archiveLookupUrl): string
    {
        try {
            return $this->fetcher->fetchFromArchive($archiveLookupUrl);
        } catch (ArchiveSnapshotNotFoundException $e) {
            $uri = Uri::new($archiveLookupUrl);
            $host = $uri->getHost() ?? '';

            if ($host === '' || str_starts_with($host, 'www.')) {
                throw $e;
            }

            return $this->fetcher->fetchFromArchive($uri->withHost("www.$host")->toString());
        }
    }

    /**
     * Returns the Article from the Wayback snapshot, or null when there is no
     * snapshot or the snapshot is still paywalled. Null means the caller should
     * continue to archive.is.
     */
    private function tryWaybackTier(string $url, string $archiveLookupUrl): ?Article
    {
        try {
            $html = $this->fetcher->fetchFromWayback($archiveLookupUrl);
        } catch (WaybackSnapshotNotFoundException) {
            return null;
        }

        $article = $this->extractor->extract($url, $html);

        return $this->paywallDetector->isGated($html, $article) ? null : $article;
    }

    private function isHardPaywallDomain(string $url): bool
    {
        $host = Uri::new($url)->getHost() ?? '';

        $host = str_starts_with($host, 'www.') ? substr($host, strlen('www.')) : $host;

        return in_array($host, $this->hardPaywallDomains, strict: true);
    }
}
