<?php

namespace App\Apis\Zyte;

use App\Apis\Scraping\Contracts\Scraper;
use App\Apis\Scraping\ScrapeResult;
use App\Apis\Scraping\ScraperException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use League\Uri\Uri;

/**
 * Client for the Zyte API, which fetches a page through Zyte's proxies and
 * optionally a browser. Zyte charges per successful response, by the target
 * site's difficulty; a rendered archive.is snapshot cost $0.002.
 *
 * Zyte forbids some domains outright (HTTP 451) and publishes no list, so a
 * forbidden domain is cached for FORBIDDEN_DOMAIN_DAYS and not requested
 * again until then.
 */
readonly class Client implements Scraper
{
    private const string ENDPOINT = 'https://api.zyte.com/v1/extract';

    private const int MAX_ATTEMPTS = 3;

    private const int CONNECT_TIMEOUT = 15;

    private const int FORBIDDEN_DOMAIN_DAYS = 30;

    public function __construct(
        private Factory $http,
        private Cache $cache,
        private ClientConfig $config,
    ) {}

    public function scrape(string $url, bool $renderJs = false): ScrapeResult
    {
        $domain = $this->domain($url);

        if ($this->cache->has($this->forbiddenKey($domain))) {
            throw new DomainForbiddenException("Zyte forbids fetching $domain.");
        }

        // Only connection failures are retried. A Zyte error is deterministic.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->toResult($this->request($url, $renderJs), $url, $domain);
            } catch (ConnectionException) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new ScraperException('Zyte connection failed after '.self::MAX_ATTEMPTS.' attempts.');
                }
            }
        }

        throw new ScraperException('Zyte request did not complete.');
    }

    private function request(string $url, bool $renderJs): Response
    {
        // The API key is the basic-auth username, with an empty password.
        return $this->http
            ->withBasicAuth($this->config->apiKey, '')
            ->timeout($this->config->timeout)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->post(self::ENDPOINT, [
                'url'                                          => $url,
                $renderJs ? 'browserHtml' : 'httpResponseBody' => true,
            ]);
    }

    private function toResult(Response $response, string $url, string $domain): ScrapeResult
    {
        if ($response->failed()) {
            $this->throwFor($response, $domain);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        // httpResponseBody is base64; browserHtml is plain.
        $content = isset($json['browserHtml'])
            ? (string) $json['browserHtml']
            : (string) base64_decode((string) ($json['httpResponseBody'] ?? ''), strict: true);

        return new ScrapeResult(
            content: $content,
            finalUrl: (string) ($json['url'] ?? $url),
            statusCode: (int) ($json['statusCode'] ?? 0),
        );
    }

    /**
     * Zyte errors are JSON with a "type" URI, a "title" and a "detail".
     */
    private function throwFor(Response $response, string $domain): never
    {
        $type = (string) $response->json('type', '');
        $title = (string) $response->json('title', $response->status());

        if ($response->status() === 451 || $type === '/download/domain-forbidden') {
            $this->cache->put($this->forbiddenKey($domain), true, now()->addDays(self::FORBIDDEN_DOMAIN_DAYS));

            throw new DomainForbiddenException("Zyte forbids fetching $domain.");
        }

        throw new ScraperException("Zyte API returned HTTP {$response->status()}: $title ($type)");
    }

    private function domain(string $url): string
    {
        $host = Uri::new($url)->getHost() ?? '';

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function forbiddenKey(string $domain): string
    {
        return "zyte:forbidden-domain:$domain";
    }
}
