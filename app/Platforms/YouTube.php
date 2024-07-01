<?php

namespace App\Platforms;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Client as YouTubeDataClient;
use App\Apis\YtDlp\Client as YtDlpClient;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use Illuminate\Support\Collection;
use League\Uri\Uri;

readonly class YouTube implements Platform
{
    use FixesUrls;

    public function __construct(
        private YtDlpClient       $ytDlp,
        private YouTubeDataClient $youTubeData,
    ) {}

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $video = $this->youTubeData->getVideoMetadata($this->getIdFromUrl($clipUrl));

            return new ClipMetadata(
                title: $video->title,
                description: $video->description,
                canonicalUrl: "https://youtube.com/watch?v=$video->id",
                source: new SourceMetadata(
                    name: $video->channel->name,
                    canonicalUrl: "https://youtube.com/channel/{$video->channel->id}",
                ),
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::YouTube, $e);
        }
    }


    private function getIdFromUrl(string $url): string
    {
        $url = $this->fixUrlSchemeAndHost($url);

        $uri = Uri::fromBaseUri($url);

        if (! collect(['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'])->contains($uri->getHost())) {
            throw new \RuntimeException("Invalid host for YouTube URL: {$uri->getHost()}");
        }

        parse_str($uri->getQuery(), $query);

        if (isset($query['v'])) {
            return $query['v'];
        }

        $splitPathPiece = fn (string $piece): string => explode('&', $piece)[0];

        /** @var Collection $pathPieces */
        $pathPieces = collect(explode('/', $uri->getPath()))->filter()->values();

        if ($pathPieces->count() == 2 && collect(['watch', 'v', 'embed', 'e', 'shorts', 'live'])->contains($pathPieces->first())) {
            return $splitPathPiece($pathPieces[1]);
        }

        if ($pathPieces->first() == 'oembed' && isset($query['url'])) {
            return $this->getIdFromUrl($query['url']);
        }

        if ($pathPieces->first() == 'attribution_link' && isset($query['u'])) {
            return $this->getIdFromUrl('https://youtube.com'.$query['u']);
        }

        if ($pathPieces->count() == 1) {
            return $splitPathPiece($pathPieces->first());
        }

        throw new \RuntimeException("Cannot parse URL: $url");
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            return $this->ytDlp->downloadAudio($clipUrl);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::YouTube, $e);
        }
    }

    // todo: this was public and part of the Platform interface. Delete?
    private function getAllClipUrls(string $sourceUrl, ?int $limit=null): array
    {
        $videoIds = $this->youTubeData->getVideoIdsForChannel(
            channelId: $this->getChannelIdFromSourceUrl($sourceUrl),
            limit: $limit,
        );

        return collect($videoIds)->map(fn (string $id) => "https://youtube.com/watch?v=$id")->all();
    }

    public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        $videoIds = $this->youTubeData->getVideoIdsForChannel(
            channelId: $this->getChannelIdFromSourceUrl($sourceUrl),
            publishedAfter: $publicationTime,
        );

        return collect($videoIds)->map(fn (string $id) => "https://youtube.com/watch?v=$id")->all();
    }

    private function sourceUrlHasChannelId(string $sourceUrl): bool
    {
        return str_contains($sourceUrl, '/channel/');
    }

    private function getLastPathPiece(string $url): string
    {
        return collect(explode('/', Uri::fromBaseUri($url)->getPath()))->last();
    }

    private function convertChannelMetadataToSourceMetadata(ChannelMetadata $channel): SourceMetadata {
        return new SourceMetadata(
            name: $channel->name,
            canonicalUrl: "https://youtube.com/channel/{$channel->id}",
        );
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        $channelIdOrHandle = $this->getLastPathPiece($sourceUrl);

        return $this->convertChannelMetadataToSourceMetadata(
            $this->sourceUrlHasChannelId($sourceUrl)
                ? $this->youTubeData->getChannelMetadataForId($channelIdOrHandle)
                : $this->youTubeData->getChannelMetadataForHandle($channelIdOrHandle)
        );
    }

    private function getChannelIdFromSourceUrl(string $sourceUrl): string
    {
        $channelIdOrHandle = $this->getLastPathPiece($sourceUrl);

        return $this->sourceUrlHasChannelId($sourceUrl)
            ? $channelIdOrHandle
            : $this->youTubeData->getChannelMetadataForHandle($channelIdOrHandle)->id;
    }
}
