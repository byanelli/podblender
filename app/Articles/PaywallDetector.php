<?php

namespace App\Articles;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Decides whether a fetched page is paywalled or missing its body, in which
 * case the Reader tries the next fetch tier. Checks run most reliable first:
 * the schema.org flag, then a word-count mismatch, then body length and
 * paywall marker strings.
 */
readonly class PaywallDetector
{
    /**
     * The minimum fraction of the declared word count that the extracted body
     * must have. Below this the body is treated as truncated by a paywall.
     */
    private const WORD_COUNT_FLOOR_RATIO = 0.5;

    public function __construct(
        private Config $config,
        private JsonLdParser $jsonLdParser,
    ) {}

    public function isGated(string $html, Article $article): bool
    {
        $jsonLd = $this->jsonLdParser->parse($html);

        // 1. The structured flag that Google specifies for paywalled content.
        if ($jsonLd->isAccessibleForFree === false) {
            return true;
        }

        // 2. The page declares a word count far larger than what was extracted.
        if ($this->wordCountFallsShort($jsonLd, $article)) {
            return true;
        }

        // 3. Least reliable: too little body, or a paywall marker in the markup.
        if (Str::length($article->text) < (int) $this->config->get('articles.min_body_length')) {
            return true;
        }

        return $this->containsPaywallMarker($html);
    }

    private function wordCountFallsShort(JsonLd $jsonLd, Article $article): bool
    {
        $declared = $jsonLd->wordCount;

        if ($declared === null || $declared === 0) {
            return false;
        }

        $extracted = str_word_count($article->text);

        return $extracted < ($declared * self::WORD_COUNT_FLOOR_RATIO);
    }

    private function containsPaywallMarker(string $html): bool
    {
        /** @var array<int, string> $markers */
        $markers = $this->config->get('articles.paywall_markers', []);

        /** @var array<int, string> $selectors */
        $selectors = $this->config->get('articles.paywall_selectors', []);

        foreach ([...$markers, ...$selectors] as $needle) {
            if ($needle !== '' && Str::contains($html, $needle, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }
}
