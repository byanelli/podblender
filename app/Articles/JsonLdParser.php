<?php

namespace App\Articles;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Reads the Article node from a page's schema.org JSON-LD. A page may have
 * several <script type="application/ld+json"> blocks. The first node with an
 * article type across all of them is used.
 */
readonly class JsonLdParser
{
    private const ARTICLE_TYPES = [
        'Article',
        'NewsArticle',
        'ReportageNewsArticle',
        'BlogPosting',
        'OpinionNewsArticle',
    ];

    public function parse(string $html): JsonLd
    {
        $node = $this->articleNode($html);

        if ($node === null) {
            return new JsonLd;
        }

        $wordCount = $node['wordCount'] ?? null;

        return new JsonLd(
            headline: $this->string($node['headline'] ?? null),
            articleBody: $this->string($node['articleBody'] ?? null),
            publisherName: $this->publisherName($node['publisher'] ?? null),
            datePublished: $this->date($node['datePublished'] ?? null),
            authors: $this->authors($node['author'] ?? null),
            wordCount: is_numeric($wordCount) ? (int) $wordCount : null,
            isAccessibleForFree: $this->isAccessibleForFree($node),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function articleNode(string $html): ?array
    {
        preg_match_all(
            '/<script[^>]*type=(["\'])application\/ld\+json\1[^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        );

        foreach ($matches[2] as $block) {
            $decoded = json_decode(trim($block), true);

            if (! is_array($decoded)) {
                continue;
            }

            // A block is either a single node, a list of nodes, or an object
            // with an "@graph" list of nodes.
            $nodes = match (true) {
                is_array($decoded['@graph'] ?? null) => $decoded['@graph'],
                array_is_list($decoded)              => $decoded,
                default                              => [$decoded],
            };

            foreach ($nodes as $node) {
                if (is_array($node) && $this->hasArticleType($node)) {
                    /** @var array<string, mixed> $node */
                    return $node;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function hasArticleType(array $node): bool
    {
        return Collection::make(Arr::wrap($node['@type'] ?? []))->intersect(self::ARTICLE_TYPES)->isNotEmpty();
    }

    private function publisherName(mixed $publisher): ?string
    {
        return is_array($publisher) ? $this->string($publisher['name'] ?? null) : null;
    }

    /**
     * @return list<string>
     */
    private function authors(mixed $author): array
    {
        // author may be a single string, a single object {name}, or a list of
        // either. Wrap a single object so it isn't iterated field by field.
        $entries = (is_array($author) && ! array_is_list($author)) ? [$author] : Arr::wrap($author);

        $authors = [];

        foreach ($entries as $entry) {
            $name = is_array($entry)
                ? $this->string($entry['name'] ?? null) ?? $this->string($entry['url'] ?? null)
                : $this->string($entry);

            if ($name !== null) {
                $authors[] = $name;
            }
        }

        return $authors;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isAccessibleForFree(array $node): ?bool
    {
        $parts = $node['hasPart'] ?? [];
        $parts = is_array($parts) ? (array_is_list($parts) ? $parts : [$parts]) : [];

        foreach ([$node, ...$parts] as $section) {
            if (is_array($section) && $this->bool($section['isAccessibleForFree'] ?? null) === false) {
                return false;
            }
        }

        return $this->bool($node['isAccessibleForFree'] ?? null);
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function date(mixed $value): ?CarbonImmutable
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

    private function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'false', 'no', '0' => false,
                'true', 'yes', '1' => true,
                default            => null,
            };
        }

        return null;
    }
}
