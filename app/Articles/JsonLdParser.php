<?php

namespace App\Articles;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Reads the Article node from a page's schema.org JSON-LD. A page may have
 * several <script type="application/ld+json"> blocks, and a node may refer to
 * a node in another block by its @id.
 */
readonly class JsonLdParser
{
    private const ARTICLE_TYPES = [
        'Article',
        'NewsArticle',
        'ReportageNewsArticle',
        'BlogPosting',
        'OpinionNewsArticle',
        'LiveBlogPosting',
    ];

    public function parse(string $html): JsonLd
    {
        $nodes = $this->nodes($html);
        $byId = $this->indexById($nodes);

        $candidates = array_values(array_filter($nodes, $this->hasArticleType(...)));

        if ($candidates === []) {
            return new JsonLd;
        }

        // Some pages put an Article with an empty body before the node that has
        // the text, e.g. a NewsArticle wrapper around a LiveBlogPosting.
        $withBody = array_values(array_filter($candidates, fn (array $node) => $this->articleBody($node) !== null));
        $node = $withBody[0] ?? $candidates[0];

        $wordCount = $node['wordCount'] ?? null;

        return new JsonLd(
            headline: $this->string($node['headline'] ?? null),
            articleBody: $this->articleBody($node),
            publisherName: $this->name($this->resolve($node['publisher'] ?? null, $byId)),
            datePublished: $this->date($node['datePublished'] ?? null),
            authors: $this->authors($node['author'] ?? null, $byId),
            wordCount: is_numeric($wordCount) ? (int) $wordCount : null,
        );
    }

    /**
     * Every top-level node across the page's JSON-LD blocks.
     *
     * @return list<array<string, mixed>>
     */
    private function nodes(string $html): array
    {
        preg_match_all(
            '/<script[^>]*type=(["\'])application\/ld\+json\1[^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        );

        $nodes = [];

        foreach ($matches[2] as $block) {
            $decoded = json_decode(trim($block), true);

            if (! is_array($decoded)) {
                continue;
            }

            // A block is either a single node, a list of nodes, or an object
            // with an "@graph" list of nodes.
            $blockNodes = match (true) {
                is_array($decoded['@graph'] ?? null) => $decoded['@graph'],
                array_is_list($decoded)              => $decoded,
                default                              => [$decoded],
            };

            foreach ($blockNodes as $node) {
                if (is_array($node)) {
                    /** @var array<string, mixed> $node */
                    $nodes[] = $node;
                }
            }
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $nodes): array
    {
        $byId = [];

        foreach ($nodes as $node) {
            $id = $node['@id'] ?? null;

            if (is_string($id) && ! isset($byId[$id])) {
                $byId[$id] = $node;
            }
        }

        return $byId;
    }

    /**
     * The node that a {"@id": ...} reference points to, or $value unchanged.
     *
     * @param  array<string, array<string, mixed>>  $byId
     */
    private function resolve(mixed $value, array $byId): mixed
    {
        if (is_array($value) && ! isset($value['name']) && is_string($value['@id'] ?? null)) {
            return $byId[$value['@id']] ?? $value;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function hasArticleType(array $node): bool
    {
        return Collection::make(Arr::wrap($node['@type'] ?? []))->intersect(self::ARTICLE_TYPES)->isNotEmpty();
    }

    /**
     * The node's articleBody. A LiveBlogPosting has none of its own, so its
     * updates are joined, oldest first.
     *
     * @param  array<string, mixed>  $node
     */
    private function articleBody(array $node): ?string
    {
        if (($body = $this->string($node['articleBody'] ?? null)) !== null) {
            return $body;
        }

        $updates = $node['liveBlogUpdate'] ?? null;

        if (! is_array($updates)) {
            return null;
        }

        $headline = $this->string($node['headline'] ?? null);
        $parts = [];

        // Updates are listed newest first.
        foreach (array_reverse(array_is_list($updates) ? $updates : [$updates]) as $update) {
            if (! is_array($update) || ($body = $this->string($update['articleBody'] ?? null)) === null) {
                continue;
            }

            // Some sites repeat the blog's headline on every update.
            $updateHeadline = $this->string($update['headline'] ?? null);

            $parts[] = $updateHeadline !== null && $updateHeadline !== $headline
                ? $this->sentence($updateHeadline).' '.$body
                : $body;
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * A headline with a final period, so narration pauses before the text.
     */
    private function sentence(string $text): string
    {
        return preg_match('/[.!?:]["\'”’]?$/u', $text) === 1 ? $text : $text.'.';
    }

    private function name(mixed $entity): ?string
    {
        return is_array($entity) ? $this->string($entity['name'] ?? null) : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     * @return list<string>
     */
    private function authors(mixed $author, array $byId): array
    {
        // author may be a single string, a single object {name}, or a list of
        // either. Wrap a single object so it isn't iterated field by field.
        $entries = (is_array($author) && ! array_is_list($author)) ? [$author] : Arr::wrap($author);

        $authors = [];

        foreach ($entries as $entry) {
            $entry = $this->resolve($entry, $byId);

            $name = is_array($entry)
                ? $this->name($entry) ?? $this->string($entry['url'] ?? null)
                : $this->string($entry);

            if ($name !== null) {
                $authors[] = $name;
            }
        }

        return $authors;
    }

    /**
     * A trimmed string with HTML entities decoded, or null if blank.
     */
    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));

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
}
