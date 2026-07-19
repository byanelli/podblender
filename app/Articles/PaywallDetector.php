<?php

namespace App\Articles;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Decides whether a directly-fetched page is gated or hollow, so the Reader
 * knows to retry through archive.is. Signals are layered strongest first: a
 * structured schema.org flag, then a word-count mismatch, then the fuzzy
 * last-resort checks on body length and paywall marker strings.
 */
readonly class PaywallDetector
{
    /**
     * The fraction of the promised word count we must actually have extracted
     * for the page to be considered whole. Below this, the body reads as
     * truncated behind a wall.
     */
    private const WORD_COUNT_FLOOR_RATIO = 0.5;

    public function __construct(private Config $config) {}

    public function isGated(string $html, Article $article): bool
    {
        $jsonLd = JsonLd::parse($html);

        // 1. Structured, Google-standard, strongest signal.
        if ($jsonLd->isAccessibleForFree() === false) {
            return true;
        }

        // 2. The page declares a word count far larger than what we extracted.
        if ($this->wordCountFallsShort($jsonLd, $article)) {
            return true;
        }

        // 3. Fuzzy last resort: too little body, or a paywall tell in the markup.
        if (Str::length($article->text) < (int) $this->config->get('articles.min_body_length')) {
            return true;
        }

        return $this->containsPaywallMarker($html);
    }

    private function wordCountFallsShort(JsonLd $jsonLd, Article $article): bool
    {
        $declared = $jsonLd->wordCount();

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
