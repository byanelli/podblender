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
 * Turns a URL and its raw HTML into an Article. The body and each metadata
 * field are extracted independently, and the first source with a usable value
 * is used. The order is schema.org JSON-LD, then OpenGraph/meta tags, then
 * heuristics on the URL's slug and host.
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

        // Deferred: when the result is unusable (e.g. a title equal to the slug
        // and a body under the minimum length), pass the raw HTML to an LLM
        // here. Do not build it inline.
    }

    // ----- Body -------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     */
    private function extractBody(?array $node, ?Readability $readability): string
    {
        // Some publishers (e.g. CNN) put the entire body in JSON-LD.
        $articleBody = $node['articleBody'] ?? null;

        if (is_string($articleBody) && trim($articleBody) !== '') {
            return $this->normalizeWhitespace($articleBody);
        }

        if ($readability !== null && ($content = $readability->getContent()) !== null) {
            // Readability returns HTML; narration needs plain text.
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
        $pageTitle = $this->pageTitle($html);

        $rawHeadline = $node['headline'] ?? null;
        $headline = (is_string($rawHeadline) && trim($rawHeadline) !== '') ? trim($rawHeadline) : null;

        $ogTitle = null;
        foreach (['og:title', 'twitter:title'] as $key) {
            if (isset($meta[$key]) && trim($meta[$key]) !== '') {
                $ogTitle = trim($meta[$key]);
                break;
            }
        }

        // Use og:title instead of the JSON-LD headline when the <title> contains
        // the og:title but not the headline. Some sites (e.g. Wikipedia) put a
        // short description in the schema.org headline. In every other case the
        // headline is used.
        if ($headline !== null && $ogTitle !== null && $pageTitle !== null
            && $this->reflectedIn($ogTitle, $pageTitle)
            && ! $this->reflectedIn($headline, $pageTitle)) {
            return $ogTitle;
        }

        if ($headline !== null) {
            return $headline;
        }

        if ($ogTitle !== null) {
            return $ogTitle;
        }

        if ($readability !== null && ($title = $readability->getTitle()) !== null && trim($title) !== '') {
            return trim($title);
        }

        if ($pageTitle !== null) {
            // The raw <title> usually includes the site name (" - Wikipedia",
            // " | The Guardian"), which can be at either end.
            return $this->stripSiteName($pageTitle, $this->siteNameCandidates($url, $node, $meta));
        }

        return $this->getNameFromSlug($url);
    }

    private function pageTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($m[1]));

        return $title !== '' ? $title : null;
    }

    /**
     * Whether $haystack contains $needle, ignoring case and runs of whitespace.
     * Used to test whether a page's <title> contains a candidate title.
     */
    private function reflectedIn(string $needle, string $haystack): bool
    {
        $normalize = fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));

        $n = $normalize($needle);

        return $n !== '' && str_contains($normalize($haystack), $n);
    }

    /**
     * Names a site might add to its <title>, most reliable first: the OpenGraph
     * site name, the JSON-LD publisher, then the host without "www.".
     *
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     * @return array<int, string>
     */
    private function siteNameCandidates(string $url, ?array $node, array $meta): array
    {
        $publisherName = null;
        $publisher = $node['publisher'] ?? null;
        if (is_array($publisher) && isset($publisher['name']) && is_string($publisher['name'])) {
            $publisherName = $publisher['name'];
        }

        $host = Uri::new($url)->getHost();
        $host = is_string($host) ? (string) preg_replace('/^www\./', '', $host) : null;

        return array_values(array_filter(
            [$meta['og:site_name'] ?? null, $publisherName, $host],
            fn ($name): bool => is_string($name) && trim($name) !== '',
        ));
    }

    /**
     * Remove a trailing "<separator> Site Name" or a leading "Site Name
     * <separator>" from a page title. Never returns an empty title.
     *
     * @param  array<int, string>  $names
     */
    private function stripSiteName(string $title, array $names): string
    {
        $separator = '[|\x{2013}\x{2014}\-:»·]';

        foreach ($names as $name) {
            $quoted = preg_quote(trim($name), '/');

            if ($quoted === '') {
                continue;
            }

            $stripped = trim((string) preg_replace(
                ['/\s+'.$separator.'\s*'.$quoted.'\s*$/iu', '/^\s*'.$quoted.'\s*'.$separator.'\s+/iu'],
                '',
                $title,
            ));

            if ($stripped !== '' && $stripped !== $title) {
                return $stripped;
            }
        }

        return $title;
    }

    // ----- Publisher --------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<string, string>  $meta
     */
    private function extractPublisher(string $url, ?array $node, array $meta): string
    {
        $publisher = $node['publisher'] ?? null;

        if (is_array($publisher) && isset($publisher['name']) && is_string($publisher['name'])) {
            $name = trim($publisher['name']);

            if ($name !== '' && ! $this->namesAnArchive($name)) {
                return $name;
            }
        }

        if (isset($meta['og:site_name'])) {
            $name = trim($meta['og:site_name']);

            if ($name !== '' && ! $this->namesAnArchive($name)) {
                return $name;
            }
        }

        // The URL is the article's even when the HTML came from an archive, so
        // its host never names the archive.
        return $this->getPublisherFromUrl($url);
    }

    /**
     * Whether this name belongs to an archiving service.
     *
     * A snapshot's og:site_name and JSON-LD publisher name the archive, and both
     * are checked before the article's URL, so without this test the article
     * would be credited to archive.ph. The archive.today mirrors are one
     * service, so every mirror is matched regardless of which one was fetched.
     */
    private function namesAnArchive(string $name): bool
    {
        $name = strtolower(trim($name));

        foreach (self::ARCHIVE_NAMES as $archive) {
            if ($name === $archive || str_starts_with($name, $archive.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names an archived page may give as its publisher. Only exact hosts and
     * service names, so a publisher called "The Archive" doesn't match.
     */
    private const ARCHIVE_NAMES = [
        'archive.ph',
        'archive.is',
        'archive.today',
        'archive.li',
        'archive.vn',
        'archive.fo',
        'archive.md',
        'web.archive.org',
        'archive.org',
        'internet archive',
        'wayback machine',
    ];

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

        // No publication date found; use the current time.
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

        // An author given as a profile URL becomes a display name derived from
        // its slug.
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
        // either. Wrap a single object so it isn't iterated field by field.
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

    // ----- Heuristics -------------------------------------------------------

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

    // ----- Helpers ----------------------------------------------------------

    private function readability(string $url, string $html): ?Readability
    {
        $readability = new Readability(new Configuration([
            'originalURL'     => $url,
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
