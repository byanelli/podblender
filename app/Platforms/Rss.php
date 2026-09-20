<?php

namespace App\Platforms;

use App\Apis\Tts\Contracts\Client as TtsApi;
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
 * An RSS/Atom feed of web articles. A feed item is a web article, so reading,
 * narrating and downloading a clip are inherited from Web. This class adds
 * subscriptions: resolving a source URL to its feed and listing new items.
 */
readonly class Rss extends Web implements SubscribablePlatform
{
    public function __construct(
        ArticleReader $reader,
        TtsApi $tts,
        Factory $http,
        private FeedParser $feedParser,
    ) {
        parent::__construct($reader, $tts, $http);
    }

    protected function type(): PlatformType
    {
        return PlatformType::Rss;
    }

    /**
     * Resolves a subscription URL to its feed, following an HTML page's
     * autodiscovery link if needed. The canonical URL returned is the feed's,
     * since it becomes the platform_url that UpdateSubscription polls.
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
            ->filter(fn (FeedItem $item) => $item->publishedAt >= $publicationTime)
            ->map(fn (FeedItem $item) => $this->clipMetadataFor($item, $source))
            ->values()
            ->all();
    }

    /**
     * Uses only the feed's metadata, so polling fetches no article pages. The
     * page is read when the download job narrates the clip.
     */
    private function clipMetadataFor(FeedItem $item, SourceMetadata $source): ClipMetadata
    {
        return new ClipMetadata(
            title: $item->title,
            description: $item->description ?? $this->describeAuthors($item->authors),
            canonicalUrl: $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($item->url)),
            publishedAt: $item->publishedAt,
            source: $source,
        );
    }

    private function sourceMetadataFor(ParsedFeed $feed, string $feedUrl): SourceMetadata
    {
        $name = $feed->title ?? (Uri::new($feedUrl)->getHost() ?? $feedUrl);

        return new SourceMetadata(
            name: $name,
            canonicalUrl: $feedUrl,
            authorName: $name,
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
