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

    public function read(string $url): Article
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
            fn () => $this->fetchAndExtract($url, $canonical),
        );
    }

    private function fetchAndExtract(string $url, string $canonical): Article
    {
        // Known-gated hosts never serve a logged-out reader a usable page, so
        // skip the doomed direct fetch and go straight to the archive.
        if ($this->isHardPaywallDomain($url)) {
            return $this->extractor->extract($url, $this->fetcher->fetchFromArchive($canonical));
        }

        $direct = $this->extractor->extract($url, $html = $this->fetcher->fetchDirect($url));

        if (! $this->paywallDetector->isGated($html, $direct)) {
            return $direct;
        }

        return $this->extractor->extract($url, $this->fetcher->fetchFromArchive($canonical));
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
