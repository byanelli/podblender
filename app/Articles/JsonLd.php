<?php

namespace App\Articles;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Parses a page's schema.org JSON-LD for the Extractor and the
 * PaywallDetector. A page may have several <script type="application/ld+json">
 * blocks, all of which are flattened into one list of nodes.
 */
readonly class JsonLd
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function __construct(private array $nodes) {}

    private const ARTICLE_TYPES = [
        'Article',
        'NewsArticle',
        'ReportageNewsArticle',
        'BlogPosting',
        'OpinionNewsArticle',
    ];

    public static function parse(string $html): self
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
            // wrapping an "@graph" list of nodes.
            $candidates = array_key_exists('@graph', $decoded)
                ? $decoded['@graph']
                : (array_is_list($decoded) ? $decoded : [$decoded]);

            foreach ($candidates as $node) {
                if (is_array($node)) {
                    /** @var array<string, mixed> $node */
                    $nodes[] = $node;
                }
            }
        }

        return new self($nodes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function articleNode(): ?array
    {
        foreach ($this->nodes as $node) {
            if ($this->nodeHasType($node, self::ARTICLE_TYPES)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * schema.org's isAccessibleForFree flag, read from the Article node and its
     * hasPart sections. False if any of them is marked paywalled; otherwise the
     * Article node's value, or null when it has none.
     */
    public function isAccessibleForFree(): ?bool
    {
        $node = $this->articleNode();

        if ($node === null) {
            return null;
        }

        foreach ([$node, ...$this->hasParts($node)] as $section) {
            $flag = $this->readBool($section['isAccessibleForFree'] ?? null);

            if ($flag === false) {
                return false;
            }
        }

        return $this->readBool($node['isAccessibleForFree'] ?? null);
    }

    public function wordCount(): ?int
    {
        $count = $this->articleNode()['wordCount'] ?? null;

        return is_numeric($count) ? (int) $count : null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function hasParts(array $node): array
    {
        $parts = $node['hasPart'] ?? [];

        if (! is_array($parts)) {
            return [];
        }

        $parts = array_is_list($parts) ? $parts : [$parts];

        return array_values(array_filter($parts, 'is_array'));
    }

    private function readBool(mixed $value): ?bool
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

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, string>  $types
     */
    private function nodeHasType(array $node, array $types): bool
    {
        $nodeTypes = Arr::wrap($node['@type'] ?? []);

        return Collection::make($nodeTypes)->intersect($types)->isNotEmpty();
    }
}
