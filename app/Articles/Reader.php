<?php

namespace App\Articles;

use App\Articles\Contracts\Fetcher;
use App\Articles\Contracts\Reader as ReaderContract;
use App\Concerns\FixesUrls;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use League\Uri\Uri;

/**
 * Orchestrates the fetch → extract → paywall-check → maybe-retry-via-archive
 * pipeline and caches the result. This is the entry point the Web platform
 * calls; everything else in App\Articles is an implementation detail behind it.
 */
readonly class Reader implements ReaderContract
{
    use FixesUrls;

    public function __construct(
        private Fetcher $fetcher,
        private Extractor $extractor,
        private PaywallDetector $paywallDetector,
        private Cache $cache,
        private Config $config,
    ) {}

    public function read(string $url, ?ArticleHints $hints = null): Article
    {
        // Two normalizations from the same URL: the www-STRIPPED form is the
        // article's identity (cache key + hard-paywall-domain check), while the
        // www-PRESERVED form is what archive.is is actually indexed by (NYT is
        // published as www.nytimes.com), so the archive lookup must use it or it
        // misses.
        $canonical = $this->removeUtmCodesFromUrl($this->ensureSchemeIsHttps($url));
        $url = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($url));

        return $this->cache->remember(
            "article:$url",
            now()->addHours((int) $this->config->get('articles.cache_ttl_hours')),
            fn () => $this->fetchAndExtract($url, $canonical, $hints),
        );
    }

    /**
     * Three fetch tiers, cheapest first, each archive tier re-validated by the
     * PaywallDetector and falling through when it comes back gated or absent:
     *
     *   1. Direct (free) — skipped for a hard-paywall domain, which never serves
     *      a logged-out reader a usable page. Returned only if not gated.
     *   2. Wayback (free) — web.archive.org is not Cloudflare-fronted, but its
     *      snapshot is often the SAME paywalled capture, so accept it only when
     *      the detector clears it; a miss or a gated snapshot falls through.
     *   3. archive.is (paid, ~55 Scrapfly credits) — the terminal backstop. Its
     *      snapshots are user-submitted un-paywalled captures, so its result is
     *      accepted as-is: there is nowhere left to fall.
     */
    private function fetchAndExtract(string $url, string $canonical, ?ArticleHints $hints): Article
    {
        if (! $this->isHardPaywallDomain($url)) {
            $direct = $this->extractor->extract($url, $html = $this->fetcher->fetchDirect($url), $hints);

            if (! $this->paywallDetector->isGated($html, $direct)) {
                return $direct;
            }
        }

        // Wayback keys on the published (www-preserved) URL, same as archive.is.
        $wayback = $this->tryWaybackTier($url, $canonical, $hints);

        if ($wayback !== null) {
            return $wayback;
        }

        return $this->extractor->extract($url, $this->fetcher->fetchFromArchive($canonical), $hints);
    }

    /**
     * Attempt the free Wayback tier: fetch the snapshot, extract it, and return
     * the Article only if the detector clears it. Returns null — meaning "fall
     * through to archive.is" — for BOTH a missing snapshot (the exception) and a
     * snapshot that is still gated or hollow. A Wayback miss never escapes.
     */
    private function tryWaybackTier(string $url, string $canonical, ?ArticleHints $hints): ?Article
    {
        try {
            $html = $this->fetcher->fetchFromWayback($canonical);
        } catch (WaybackSnapshotNotFoundException) {
            return null;
        }

        $article = $this->extractor->extract($url, $html, $hints);

        return $this->paywallDetector->isGated($html, $article) ? null : $article;
    }

    private function isHardPaywallDomain(string $url): bool
    {
        $host = Uri::new($url)->getHost() ?? '';

        $host = str_starts_with($host, 'www.') ? substr($host, strlen('www.')) : $host;

        /** @var array<int, string> $domains */
        $domains = $this->config->get('articles.hard_paywall_domains', []);

        return in_array($host, $domains, strict: true);
    }
}
