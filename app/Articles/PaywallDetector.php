<?php

namespace App\Articles;

use Illuminate\Support\Str;

/**
 * Decides whether a fetched page is paywalled or missing its body, in which
 * case the Reader tries the next fetch tier. Checks run most reliable first:
 * a word-count mismatch, then body length, then paywall prompts and
 * containers in the page.
 */
readonly class PaywallDetector
{
    /**
     * The minimum fraction of the declared word count that the extracted body
     * must have. Below this the body is treated as truncated by a paywall.
     */
    private const WORD_COUNT_FLOOR_RATIO = 0.5;

    /** How far from the start or end of the article a paywall prompt can be, in characters. */
    private const PROMPT_WINDOW = 300;

    public function __construct(
        private JsonLdParser $jsonLdParser,
        private PaywallDetectorConfig $config,
    ) {}

    public function isGated(string $html, Article $article): bool
    {
        $jsonLd = $this->jsonLdParser->parse($html);

        // 1. The page declares a word count far larger than what was extracted.
        if ($this->wordCountFallsShort($jsonLd, $article)) {
            return true;
        }

        // 2. Too little body.
        if (Str::length($article->text) < $this->config->minBodyLength) {
            return true;
        }

        // 3. Least reliable: a paywall prompt or container in the page.
        return $this->showsPaywallPrompt($html, $article) || $this->containsPaywallSelector($html);
    }

    private function wordCountFallsShort(JsonLd $jsonLd, Article $article): bool
    {
        if ($jsonLd->wordCount === null || $jsonLd->wordCount === 0) {
            return false;
        }

        return str_word_count($article->text) < ($jsonLd->wordCount * self::WORD_COUNT_FLOOR_RATIO);
    }

    /**
     * Whether the page's visible text has a paywall marker phrase. The
     * extracted article can include the prompt, at its start (NYT) or its end
     * (Substack), so a phrase in the article counts only within PROMPT_WINDOW
     * characters of either end.
     */
    private function showsPaywallPrompt(string $html, Article $article): bool
    {
        $visibleText = $this->visibleText($html);
        $body = mb_strtolower($article->text);

        foreach ($this->config->markers as $marker) {
            if ($marker === '' || ! Str::contains($visibleText, $marker, ignoreCase: true)) {
                continue;
            }

            $first = mb_strpos($body, mb_strtolower($marker));
            $last = mb_strrpos($body, mb_strtolower($marker));

            if ($first === false
                || $first < self::PROMPT_WINDOW
                || $last >= mb_strlen($body) - self::PROMPT_WINDOW) {
                return true;
            }
        }

        return false;
    }

    private function containsPaywallSelector(string $html): bool
    {
        foreach ($this->config->selectors as $selector) {
            if ($selector !== '' && Str::contains($html, $selector, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }

    private function visibleText(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|noscript|template|svg)\b.*?</\1\s*>#is', ' ', $html);

        return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
    }
}
