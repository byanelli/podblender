<?php

namespace App\Articles;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Decides whether a fetched page is paywalled or missing its body, in which
 * case the Reader tries the next fetch tier. Checks run most reliable first:
 * a word-count mismatch, then body length, then the schema.org flag, then
 * paywall prompts and containers in the page.
 */
readonly class PaywallDetector
{
    /**
     * The minimum fraction of the declared word count that the extracted body
     * must have. Below this the body is treated as truncated by a paywall.
     */
    private const WORD_COUNT_FLOOR_RATIO = 0.5;

    /** How close to the end of the article a paywall prompt can be, in characters. */
    private const PROMPT_WINDOW = 300;

    public function __construct(
        private Config $config,
        private JsonLdParser $jsonLdParser,
    ) {}

    public function isGated(string $html, Article $article): bool
    {
        $jsonLd = $this->jsonLdParser->parse($html);

        // 1. The page declares a word count far larger than what was extracted.
        if ($this->wordCountFallsShort($jsonLd, $article)) {
            return true;
        }

        // 2. Too little body.
        if (Str::length($article->text) < (int) $this->config->get('articles.min_body_length')) {
            return true;
        }

        // 3. The flag Google specifies for paywalled content. Metered sites set
        // it on pages that still contain the whole article, so it counts only
        // when nothing shows the body is complete.
        if ($jsonLd->isAccessibleForFree === false && ! $this->bodyIsComplete($jsonLd, $article)) {
            return true;
        }

        // 4. Least reliable: a paywall prompt or container in the page.
        return $this->showsPaywallPrompt($html, $article) || $this->containsPaywallSelector($html);
    }

    private function wordCountFallsShort(JsonLd $jsonLd, Article $article): bool
    {
        if ($jsonLd->wordCount === null || $jsonLd->wordCount === 0) {
            return false;
        }

        return str_word_count($article->text) < ($jsonLd->wordCount * self::WORD_COUNT_FLOOR_RATIO);
    }

    private function bodyIsComplete(JsonLd $jsonLd, Article $article): bool
    {
        return $jsonLd->articleBody !== null
            || ($jsonLd->wordCount !== null && ! $this->wordCountFallsShort($jsonLd, $article));
    }

    /**
     * Whether the page's visible text has a paywall marker phrase. The
     * extracted article can include the prompt at its end, so a phrase in the
     * article counts only in its last PROMPT_WINDOW characters.
     */
    private function showsPaywallPrompt(string $html, Article $article): bool
    {
        /** @var array<int, string> $markers */
        $markers = $this->config->get('articles.paywall_markers', []);

        $visibleText = $this->visibleText($html);
        $body = mb_strtolower($article->text);

        foreach ($markers as $marker) {
            if ($marker === '' || ! Str::contains($visibleText, $marker, ignoreCase: true)) {
                continue;
            }

            $position = mb_strrpos($body, mb_strtolower($marker));

            if ($position === false || $position >= mb_strlen($body) - self::PROMPT_WINDOW) {
                return true;
            }
        }

        return false;
    }

    private function containsPaywallSelector(string $html): bool
    {
        /** @var array<int, string> $selectors */
        $selectors = $this->config->get('articles.paywall_selectors', []);

        foreach ($selectors as $selector) {
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
