<?php

namespace App\Platforms\Feeds;

use Carbon\CarbonImmutable;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Exception\ExceptionInterface;
use Laminas\Feed\Reader\Reader;
use League\Uri\Uri;

/**
 * Thin adapter over laminas-feed: raw XML in, ParsedFeed/FeedItem out. The
 * library absorbs the wilderness of feed formats (RSS 0.9x/1.0/2.0, Atom,
 * dc: fallbacks, encodings); this class normalizes its loosely-typed output
 * into value objects so the rest of the app never touches laminas types.
 */
class FeedParser
{
    private const array FEED_MIME_TYPES = ['application/rss+xml', 'application/atom+xml'];

    /**
     * Is this body a feed at all? Cheap structural sniff — well-formed XML
     * whose root element is one of the three feed vocabularies — used by
     * getSourceMetadata to decide between parsing and autodiscovery. An
     * (X)HTML page fails this either by not being well-formed XML or by
     * having an <html> root.
     */
    public function isFeed(string $body): bool
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;

            if (! $document->loadXML($body)) {
                return false;
            }

            return in_array($document->documentElement?->localName, ['rss', 'feed', 'RDF'], strict: true);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Parse a feed into its valid items. An entry missing its link, title, or
     * publication date is silently dropped — we won't fetch an article page
     * just to reconstruct metadata the feed was supposed to supply.
     *
     * @throws ExceptionInterface when the body isn't a parseable feed
     */
    public function parse(string $body): ParsedFeed
    {
        $feed = Reader::importString($body);

        $items = [];

        foreach ($feed as $entry) {
            /** @var EntryInterface $entry */
            $url = $this->presence($entry->getLink());
            $title = $this->presence($entry->getTitle());
            $publishedAt = $this->date($entry);

            if ($url === null || $title === null || $publishedAt === null) {
                continue;
            }

            $items[] = new FeedItem(
                url: $url,
                title: $title,
                publishedAt: $publishedAt,
                description: $this->summarize($entry->getDescription() ?? $entry->getContent()),
                authors: $this->authors($entry),
            );
        }

        return new ParsedFeed(
            title: $this->presence($feed->getTitle()),
            items: $items,
        );
    }

    /**
     * Find the feed an HTML page advertises: the standard autodiscovery
     * <link rel="alternate" type="application/rss+xml|atom+xml" href="..."> in
     * its head. Returns the absolute feed URL, or null when the page
     * advertises none.
     */
    public function discoverFeedUrl(string $html, string $pageUrl): ?string
    {
        preg_match_all('/<link\b[^>]*>/is', $html, $tags);

        foreach ($tags[0] as $tag) {
            $rel = $this->attr($tag, 'rel');
            $type = $this->attr($tag, 'type');
            $href = $this->attr($tag, 'href');

            if ($rel !== null && $type !== null && $href !== null && trim($href) !== ''
                && str_contains(strtolower($rel), 'alternate')
                && in_array(strtolower(trim($type)), self::FEED_MIME_TYPES, strict: true)) {
                return Uri::fromBaseUri(html_entity_decode(trim($href)), $pageUrl)->toString();
            }
        }

        return null;
    }

    private function presence(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Feed descriptions are routinely HTML (or a whole article body); a clip
     * description wants a line of plain text.
     */
    private function summarize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value))));

        return $text !== '' ? $text : null;
    }

    private function date(EntryInterface $entry): ?CarbonImmutable
    {
        try {
            $date = $entry->getDateCreated() ?? $entry->getDateModified();
        } catch (\Exception) {
            // laminas throws on a malformed date; a bad date is no date.
            return null;
        }

        return $date !== null ? CarbonImmutable::instance($date) : null;
    }

    /**
     * @return array<int, string>
     */
    private function authors(EntryInterface $entry): array
    {
        $authors = [];

        foreach ($entry->getAuthors() ?? [] as $author) {
            $name = is_array($author) ? ($author['name'] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                $authors[] = trim($name);
            }
        }

        return $authors;
    }

    private function attr(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $tag, $m) === 1) {
            return $m[2];
        }

        return null;
    }
}
