<?php

namespace App\Articles;

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
    public function __construct(
        private JsonLdParser $jsonLdParser,
        private MetaTagParser $metaTagParser,
    ) {}

    public function extract(string $url, string $html): Article
    {
        $jsonLd = $this->jsonLdParser->parse($html);
        $meta = $this->metaTagParser->parse($html);
        $readability = $this->readability($url, $html);

        return new Article(
            url: $url,
            title: $this->extractTitle($url, $jsonLd, $meta, $html, $readability),
            publisher: $this->extractPublisher($url, $jsonLd, $meta),
            publicationDate: $jsonLd->datePublished
                ?? $meta->articlePublishedTime
                ?? $meta->ogPublishedTime,
            authors: $this->extractAuthors($jsonLd, $meta),
            text: $this->extractBody($jsonLd, $readability),
        );

        // Deferred: when the result is unusable (e.g. a title equal to the slug
        // and a body under the minimum length), pass the raw HTML to an LLM
        // here. Do not build it inline.
    }

    // ----- Body -------------------------------------------------------------

    private function extractBody(JsonLd $jsonLd, ?Readability $readability): string
    {
        // Some publishers (e.g. CNN) put the entire body in JSON-LD.
        if ($jsonLd->articleBody !== null) {
            return $this->collapseWhitespace($jsonLd->articleBody);
        }

        if ($readability !== null && ($content = $readability->getContent()) !== null) {
            // Readability returns HTML; narration needs plain text.
            return $this->collapseWhitespace(html_entity_decode(strip_tags($content)));
        }

        return '';
    }

    // ----- Title ------------------------------------------------------------

    private function extractTitle(
        string $url,
        JsonLd $jsonLd,
        MetaTags $meta,
        string $html,
        ?Readability $readability,
    ): string {
        $pageTitle = $this->pageTitle($html);
        $headline = $jsonLd->headline;
        $ogTitle = $meta->ogTitle ?? $meta->twitterTitle;

        // Use og:title instead of the JSON-LD headline when the <title> contains
        // the og:title but not the headline. Some sites (e.g. Wikipedia) put a
        // short description in the schema.org headline. In every other case the
        // headline is used.
        if ($headline !== null && $ogTitle !== null && $pageTitle !== null
            && $this->reflectedIn($ogTitle, $pageTitle)
            && ! $this->reflectedIn($headline, $pageTitle)) {
            return $this->stripSiteName($ogTitle, $this->siteNameCandidates($url, $jsonLd, $meta));
        }

        if ($headline !== null) {
            return $headline;
        }

        if ($ogTitle !== null) {
            // og:title can include the site name, as Wikipedia's does.
            return $this->stripSiteName($ogTitle, $this->siteNameCandidates($url, $jsonLd, $meta));
        }

        if ($readability !== null && ($title = $readability->getTitle()) !== null && trim($title) !== '') {
            return trim($title);
        }

        if ($pageTitle !== null) {
            // The raw <title> usually includes the site name (" - Wikipedia",
            // " | The Guardian"), which can be at either end.
            return $this->stripSiteName($pageTitle, $this->siteNameCandidates($url, $jsonLd, $meta));
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
     * Names a site might add to its titles, most reliable first: the OpenGraph
     * site name, the JSON-LD publisher, the host without "www.", and the host's
     * second-level label ("wikipedia" for en.wikipedia.org).
     *
     * @return list<string>
     */
    private function siteNameCandidates(string $url, JsonLd $jsonLd, MetaTags $meta): array
    {
        $host = Uri::new($url)->getHost();
        $host = is_string($host) ? (string) preg_replace('/^www\./', '', $host) : null;

        $labels = explode('.', $host ?? '');
        $domainLabel = count($labels) >= 2 ? $labels[count($labels) - 2] : null;

        return array_values(array_filter(
            [$meta->ogSiteName, $jsonLd->publisherName, $host, $domainLabel],
            fn (?string $name): bool => $name !== null && $name !== '',
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

    private function extractPublisher(string $url, JsonLd $jsonLd, MetaTags $meta): string
    {
        foreach ([$jsonLd->publisherName, $meta->ogSiteName] as $name) {
            if ($name !== null && ! $this->isArchiveName($name)) {
                return $name;
            }
        }

        // The URL is the article's even when the HTML came from an archive, so
        // its host is the publisher's.
        return $this->getPublisherFromUrl($url);
    }

    /**
     * Whether this name belongs to an archiving service.
     *
     * A snapshot's og:site_name and JSON-LD publisher contain the archive's
     * name, and both are checked before the article's URL, so without this
     * test the article would be credited to archive.ph. The archive.today
     * mirrors are one service, so every mirror is matched regardless of which
     * one was fetched.
     */
    private function isArchiveName(string $name): bool
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

    // ----- Authors ----------------------------------------------------------

    /**
     * @return list<string>
     */
    private function extractAuthors(JsonLd $jsonLd, MetaTags $meta): array
    {
        $authors = $jsonLd->authors;

        if ($authors === []) {
            $authors = $meta->author !== null ? [$meta->author] : $meta->articleAuthors;
        }

        // An author given as a profile URL becomes a display name derived from
        // its slug.
        return array_map(
            fn (string $author) => $this->isUrl($author) ? $this->getNameFromSlug($author) : $author,
            $authors,
        );
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

    private function collapseWhitespace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
