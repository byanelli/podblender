<?php

namespace App\Platforms;

use App\Apis\Whisper\Contracts\Client as WhisperApi;
use App\Articles\ArticleHints;
use App\Articles\Contracts\Reader as ArticleReader;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Exceptions\FeedNotFoundException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;
use App\Platforms\Feeds\FeedItem;
use App\Platforms\Feeds\FeedParser;
use App\Platforms\Feeds\ParsedFeed;
use Illuminate\Http\Client\Factory;
use League\Uri\Uri;

/**
 * An RSS/Atom feed of web articles. Extends Web because a feed item IS a web
 * article — reading, narrating, and downloading a clip are inherited verbatim —
 * and adds the subscription side: resolving a source URL to its feed (with
 * autodiscovery from an HTML page) and polling that feed for new items.
 */
readonly class Rss extends Web implements SubscribablePlatform
{
    public function __construct(
        ArticleReader $reader,
        WhisperApi $whisper,
        Factory $http,
        private FeedParser $feedParser,
    ) {
        parent::__construct($reader, $whisper, $http);
    }

    protected function type(): PlatformType
    {
        return PlatformType::Rss;
    }

    /**
     * Resolve a subscription URL to its feed. The URL may already be the feed;
     * when it's an ordinary page instead, follow the page's autodiscovery link.
     * The canonical URL returned here is the FEED's URL — it becomes the
     * source's platform_url, which is what UpdateSubscription later polls.
     */
    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        try {
            $sourceUrl = $this->removeUtmCodesFromUrl($this->ensureSchemeIsHttps($sourceUrl));

            $body = $this->fetch($sourceUrl);

            if (! $this->feedParser->isFeed($body)) {
                $sourceUrl = $this->feedParser->discoverFeedUrl($body, $sourceUrl)
                    ?? throw new FeedNotFoundException($sourceUrl);

                $body = $this->fetch($sourceUrl);
            }

            return $this->sourceMetadataFor($this->feedParser->parse($body), $sourceUrl);
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Metadata, $e);
        }
    }

    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        $feed = $this->feedParser->parse($this->fetch($sourceUrl));

        $source = $this->sourceMetadataFor($feed, $sourceUrl);

        return collect($feed->items)
            // An item the feed itself dates as old is skipped without ever
            // touching its article page; an undated one survives to be dated
            // by the page below.
            ->filter(fn (FeedItem $item) => $item->publishedAt === null || $item->publishedAt >= $publicationTime)
            ->map(fn (FeedItem $item) => $this->clipMetadataFor($item, $source))
            ->filter(fn (ClipMetadata $clip) => $clip->publishedAt >= $publicationTime)
            ->values()
            ->all();
    }

    /**
     * Build a clip from a feed item. A complete item never costs a page fetch:
     * the feed's own title/date/description are publisher-authored and
     * item-specific, so they're used as-is. An incomplete item is filled in by
     * reading the article, with whatever the feed DID say forwarded as hints
     * that outrank the page's own metadata — a site with a dirty page but a
     * clean feed still yields clean clips. Reading here also primes the article
     * cache the download job will draw from.
     */
    private function clipMetadataFor(FeedItem $item, SourceMetadata $source): ClipMetadata
    {
        $clipUrl = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($item->url));

        if ($item->title !== null && $item->publishedAt !== null) {
            return new ClipMetadata(
                title: $item->title,
                description: $item->description ?? $this->describeAuthors($item->authors),
                canonicalUrl: $clipUrl,
                publishedAt: $item->publishedAt,
                source: $source,
            );
        }

        $article = $this->reader->read($clipUrl, new ArticleHints(
            title: $item->title,
            authors: $item->authors,
            publicationDate: $item->publishedAt,
        ));

        return new ClipMetadata(
            title: $article->title,
            description: $item->description ?? $this->describeAuthors($article->authors),
            canonicalUrl: $clipUrl,
            publishedAt: $article->publicationDate,
            source: $source,
        );
    }

    private function sourceMetadataFor(ParsedFeed $feed, string $feedUrl): SourceMetadata
    {
        return new SourceMetadata(
            name: $feed->title ?? (Uri::new($feedUrl)->getHost() ?? $feedUrl),
            canonicalUrl: $feedUrl,
        );
    }

    /**
     * @param  array<int, string>  $authors
     */
    private function describeAuthors(array $authors): string
    {
        return $authors === [] ? 'Article' : 'Article by '.collect($authors)->join(' and ');
    }

    private function fetch(string $url): string
    {
        return $this->http
            ->withHeaders(['User-Agent' => (string) config('articles.user_agent')])
            ->timeout(30)
            ->get($url)
            ->throw()
            ->body();
    }
}
