<?php

namespace App\Apis\ArticleExtractor;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Uri\Uri;

readonly class Client
{
    public function __construct(
        private Factory $http,
        private Config  $config,
        private Cache   $cache,
    ) {}

    private function getCacheKey(string $url): string {
        return 'article:'.$url;
    }

    public function getApiKey(): string {
        return $this->config->get('apify.api_key');
    }

    private function getCachedResponse(string $url): ?array {
        return $this->cache->get($this->getCacheKey($url));
    }

    private function cacheResponse(string $url, array $response): void {
        $this->cache->put($this->getCacheKey($url), $response);
    }

    private function getArticleFromApi(string $url): array {
        if (!is_null($cached = $this->getCachedResponse($url))) {
            return $cached;
        }

        $response = $this->http
            ->withQueryParameters(['token' => $this->getApiKey()])
            ->withBody(json_encode(['articleUrls' => [['url' => $url]]]))
            ->timeout(60)
            ->post('https://api.apify.com/v2/acts/lukaskrivka~article-extractor-smart/run-sync-get-dataset-items')
            ->json();

        !empty($response) || throw new \RuntimeException("Failed to retrieve article: $url");
        count($response) == 1 || throw new \RuntimeException("Unexpected found more than one result for article: $url");

        $response = $response[0];

        $this->cacheResponse($url, $response);

        return $response;
    }

    public function getArticle(string $url): Article {
        $response = $this->getArticleFromApi($url);

        return new Article(
            url: $url,
            title: $response['title'] ?: $this->getNameFromSlug($url),
            publisher: $response['publisher'] ?: $this->getPublisherFromUrl($url),
            authors: $this->parseAuthors($response['author']),
            text: $response['text'],
        );
    }

    private function getPublisherFromUrl(string $url): string {
        return Str::of(Uri::new($url)->getHost())
            ->replaceMatches('/^www\\./', '')
            ->__toString();
    }

    private function getNameFromSlug(string $url): string {
        $path = Uri::new($url)->getPath();

        $slug = Arr::last(explode('/', $path));

        return str_contains($slug, '-')
            ? collect(explode('-', $slug))->map(fn($s) => ucfirst($s))->implode(' ')
            : $slug;
    }

    private function isUrl(string $url): bool {
        return Str::startsWith($url, ['http://', 'https://']);
    }

    /**
     * @param array<int, string> $authors
     * @return array<int, string>
     */
    private function parseAuthors(array $authors): array {
        return collect($authors)->map(function (string $author) {
            return $this->isUrl($author)
                ? $this->getNameFromSlug($author)
                : $author;
        })->toArray();
    }
}
