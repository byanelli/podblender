<?php

namespace App\Articles;

use Carbon\CarbonImmutable;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Uri\Uri;

/**
 * Turns a URL and its raw HTML into an Article. Each field is produced by its
 * own cascade over the same HTML — the body and the metadata are extracted
 * independently, and within each the first source that yields a usable value
 * wins. The order is: schema.org JSON-LD, then OpenGraph/meta tags, then the
 * slug/host heuristics ported from the retired extraction API as a last resort.
 */
readonly class Extractor
{
    public function extract(string $url, string $html): Article
    {
        $jsonLd = JsonLd::parse($html);
        $node = $jsonLd->articleNode();

        $readability = $this->readability($url, $html);
        $meta = $this->parseMetaTags($html);

        return new Article(
            url: $url,
            title: $this->extractTitle($url, $node, $meta, $html, $readability),
            publisher: $this->extractPublisher($url, $node, $meta),
            publicationDate: $this->extractDate($node, $meta),
            authors: $this->extractAuthors($node, $meta),
            text: $this->extractBody($node, $readability),
        );

        // Extension point: a future LLM backstop rung would go here — when this
        // cascade yields junk (e.g. a title equal to the slug and a body under
        // the minimum length), hand the raw HTML to an LLM to extract the
        // article. Deferred by the owner; do not build it inline.
    }

    // ----- Body -------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     */
    private function extractBody(?array $node, ?Readability $readability): string
    {
        // Some publishers (e.g. CNN) ship the entire body in JSON-LD.
        $articleBody = $node['articleBody'] ?? null;

        if (is_string($articleBody) && trim($articleBody) !== '') {
            return $this->normalizeWhitespace($articleBody);
        }

        if ($readability !== null && ($content = $readability->getContent()) !== null) {
            // Readability returns HTML; narration wants plain text.
            return $this->normalizeWhitespace(strip_tags($content));
        }

        return '';
    }

    // ----- Title ------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     */
    private function extractTitle(string $url, ?array $node, array $meta, string $html, ?Readability $readability): string
    {
        $headline = $node['headline'] ?? null;

        if (is_string($headline) && trim($headline) !== '') {
            return trim($headline);
        }

        foreach (['og:title', 'twitter:title'] as $key) {
            if (isset($meta[$key]) && trim($meta[$key]) !== '') {
                return trim($meta[$key]);
            }
        }

        if ($readability !== null && ($title = $readability->getTitle()) !== null && trim($title) !== '') {
            return trim($title);
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            $title = trim(html_entity_decode($m[1]));

            if ($title !== '') {
                return $title;
            }
        }

        return $this->getNameFromSlug($url);
    }

    // ----- Publisher --------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     */
    private function extractPublisher(string $url, ?array $node, array $meta): string
    {
        $publisher = $node['publisher'] ?? null;

        if (is_array($publisher) && isset($publisher['name']) && is_string($publisher['name']) && trim($publisher['name']) !== '') {
            return trim($publisher['name']);
        }

        if (isset($meta['og:site_name']) && trim($meta['og:site_name']) !== '') {
            return trim($meta['og:site_name']);
        }

        return $this->getPublisherFromUrl($url);
    }

    // ----- Date -------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     */
    private function extractDate(?array $node, array $meta): CarbonImmutable
    {
        $candidates = [
            $node['datePublished'] ?? null,
            $meta['article:published_time'] ?? null,
            $meta['og:published_time'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                try {
                    return CarbonImmutable::parse($candidate);
                } catch (\Exception) {
                    continue;
                }
            }
        }

        // Nothing said when it was published; treat it as now rather than fail.
        return CarbonImmutable::now();
    }

    // ----- Authors ----------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     * @return array<int, string>
     */
    private function extractAuthors(?array $node, array $meta): array
    {
        $authors = $this->authorsFromJsonLd($node['author'] ?? null);

        if ($authors === [] && isset($meta['author']) && trim($meta['author']) !== '') {
            $authors = [$meta['author']];
        }

        if ($authors === [] && isset($meta['article:author']) && trim($meta['article:author']) !== '') {
            $authors = [$meta['article:author']];
        }

        // Ported behavior: an author expressed as a profile URL becomes a
        // display name derived from its slug.
        return array_values(array_map(
            fn (string $author) => $this->isUrl($author) ? $this->getNameFromSlug($author) : trim($author),
            array_filter($authors, fn (string $a) => trim($a) !== ''),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function authorsFromJsonLd(mixed $author): array
    {
        if ($author === null) {
            return [];
        }

        // author may be a single string, a single object {name}, or a list of
        // either. Wrap a lone associative object so it isn't iterated field by
        // field.
        $entries = (is_array($author) && ! array_is_list($author)) ? [$author] : Arr::wrap($author);

        $authors = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $authors[] = $entry;
            } elseif (is_array($entry) && isset($entry['name']) && is_string($entry['name'])) {
                $authors[] = $entry['name'];
            } elseif (is_array($entry) && isset($entry['url']) && is_string($entry['url'])) {
                $authors[] = $entry['url'];
            }
        }

        return $authors;
    }

    // ----- Heuristics (ported verbatim in spirit from the old extraction API) --

    private function getPublisherFromUrl(string $url): string
    {
        return Str::of(Uri::new($url)->getHost() ?? '')
            ->replaceMatches('/^www\\./', '')
            ->__toString();
    }

    private function getNameFromSlug(string $url): string
    {
        $path = Uri::new($url)->getPath();

        $slug = Arr::last(explode('/', $path)) ?? '';

        return str_contains($slug, '-')
            ? collect(explode('-', $slug))->map(fn ($s) => ucfirst($s))->implode(' ')
            : $slug;
    }

    private function isUrl(string $url): bool
    {
        return Str::startsWith($url, ['http://', 'https://']);
    }

    // ----- Plumbing ---------------------------------------------------------

    private function readability(string $url, string $html): ?Readability
    {
        $readability = new Readability(new Configuration([
            'originalURL' => $url,
            'fixRelativeURLs' => true,
        ]));

        try {
            return $readability->parse($html) ? $readability : null;
        } catch (ParseException) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseMetaTags(string $html): array
    {
        preg_match_all('/<meta\b[^>]*>/is', $html, $tags);

        $meta = [];

        foreach ($tags[0] as $tag) {
            $key = $this->attr($tag, 'property') ?? $this->attr($tag, 'name');
            $content = $this->attr($tag, 'content');

            if ($key !== null && $content !== null) {
                $meta[strtolower($key)] = html_entity_decode($content);
            }
        }

        return $meta;
    }

    private function attr(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $tag, $m) === 1) {
            return $m[2];
        }

        return null;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = html_entity_decode($text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
