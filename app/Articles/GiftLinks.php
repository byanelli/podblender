<?php

namespace App\Articles;

use Illuminate\Container\Attributes\Config;
use League\Uri\Uri;

/**
 * Recognizes publishers' gift links by the query parameters listed in
 * articles.gift_link_params.
 */
readonly class GiftLinks
{
    public function __construct(
        /** @var array<string, list<string>> */
        #[Config('articles.gift_link_params')] private array $params,
    ) {}

    public function isGiftLink(string $url): bool
    {
        return array_intersect_key($this->query($url), array_flip($this->paramsFor($url))) !== [];
    }

    public function removeGiftParams(string $url): string
    {
        $query = $this->query($url);
        $kept = array_diff_key($query, array_flip($this->paramsFor($url)));

        if (count($kept) === count($query)) {
            return $url;
        }

        return Uri::new($url)->withQuery($kept === [] ? null : http_build_query($kept))->toString();
    }

    /**
     * @return list<string>
     */
    private function paramsFor(string $url): array
    {
        $host = Uri::new($url)->getHost() ?? '';
        $host = str_starts_with($host, 'www.') ? substr($host, strlen('www.')) : $host;

        return $this->params[$host] ?? [];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function query(string $url): array
    {
        parse_str(Uri::new($url)->getQuery() ?? '', $query);

        return $query;
    }
}
