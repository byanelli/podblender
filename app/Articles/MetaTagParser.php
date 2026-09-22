<?php

namespace App\Articles;

use Carbon\CarbonImmutable;

readonly class MetaTagParser
{
    public function parse(string $html): MetaTags
    {
        $tags = $this->tags($html);

        return new MetaTags(
            ogTitle: $this->string($tags['og:title'] ?? null),
            twitterTitle: $this->string($tags['twitter:title'] ?? null),
            ogSiteName: $this->string($tags['og:site_name'] ?? null),
            author: $this->string($tags['author'] ?? null),
            articleAuthors: $this->articleAuthors($tags['article:author'] ?? null),
            articlePublishedTime: $this->date($tags['article:published_time'] ?? null),
            ogPublishedTime: $this->date($tags['og:published_time'] ?? null),
        );
    }

    /**
     * Each tag's content keyed by its lowercased property or name attribute.
     *
     * @return array<string, string>
     */
    private function tags(string $html): array
    {
        preg_match_all('/<meta\b[^>]*>/is', $html, $matches);

        $tags = [];

        foreach ($matches[0] as $tag) {
            $key = $this->attribute($tag, 'property') ?? $this->attribute($tag, 'name');
            $content = $this->attribute($tag, 'content');

            if ($key !== null && $content !== null) {
                $tags[strtolower($key)] = html_entity_decode($content);
            }
        }

        return $tags;
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $tag, $m) === 1) {
            return $m[2];
        }

        return null;
    }

    /**
     * Some sites put every author's profile URL in one tag, separated by
     * commas. Anything else is kept whole, since a name can contain a comma.
     *
     * @return list<string>
     */
    private function articleAuthors(?string $value): array
    {
        $value = $this->string($value);

        if ($value === null) {
            return [];
        }

        $parts = array_values(array_filter(array_map(trim(...), explode(',', $value))));
        $allUrls = collect($parts)->every(fn (string $part) => preg_match('#^https?://#i', $part) === 1);

        return $allUrls ? $parts : [$value];
    }

    private function string(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value !== '' ? $value : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        $value = $this->string($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
}
