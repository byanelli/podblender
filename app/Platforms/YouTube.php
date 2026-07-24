<?php

namespace App\Platforms;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Contracts\Client as YouTubeDataClient;
use App\Apis\YouTubeData\VideoMetadata;
use App\Apis\YtDlp\Client as YtDlpClient;
use App\Apis\YtDlp\MembersOnlyContentException;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;
use Illuminate\Support\Collection;
use League\Uri\Uri;

readonly class YouTube implements SubscribablePlatform
{
    use FixesUrls;

    public function __construct(
        private YtDlpClient $ytDlp,
        private YouTubeDataClient $youTubeData,
    ) {}

    private function convertVideoMetadataToClipMetadata(VideoMetadata $video): ClipMetadata
    {
        return new ClipMetadata(
            title: $video->title,
            description: $video->description,
            canonicalUrl: "https://youtube.com/watch?v=$video->id",
            publishedAt: $video->publishedAt,
            source: $this->convertChannelMetadataToSourceMetadata($video->channel),
            estimatedDownloadTime: $this->estimateDownloadTime($video),
        );
    }

    /**
     * A conservative guess at one download's wall-clock time. yt-dlp pulls the
     * audio track (~160 kbps) and paces its own requests, so assume a slow
     * connection (2 Mbps usable) plus fixed overhead, and lean on the flat
     * default when the API didn't report a duration.
     */
    private function estimateDownloadTime(VideoMetadata $video): ?int
    {
        if ($video->durationSeconds === null) {
            return null;
        }

        $audioBytes = $video->durationSeconds * self::AUDIO_BITRATE_BYTES_PER_SECOND;

        return (int) ceil($audioBytes / self::ASSUMED_DOWNLOAD_BYTES_PER_SECOND)
            + self::DOWNLOAD_OVERHEAD_SECONDS;
    }

    private const AUDIO_BITRATE_BYTES_PER_SECOND = 20_000; // ~160 kbps

    private const ASSUMED_DOWNLOAD_BYTES_PER_SECOND = 250_000; // ~2 Mbps

    private const DOWNLOAD_OVERHEAD_SECONDS = 60;

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            return $this->convertVideoMetadataToClipMetadata(
                $this->youTubeData->getVideoMetadata($this->getIdFromUrl($clipUrl))
            );
        } catch (\Exception $e) {
            throw new PlatformException(PlatformType::YouTube, PlatformOperation::Metadata, $e);
        }
    }

    private function getIdFromUrl(string $url): string
    {
        $url = $this->fixUrlSchemeAndHost($url);

        $uri = Uri::new($url);

        if (! collect(Platforms::YOUTUBE_HOSTS)->contains($uri->getHost() ?? '')) {
            throw new \RuntimeException("Invalid host for YouTube URL: {$uri->getHost()}");
        }

        parse_str($uri->getQuery() ?? '', $query);

        if (isset($query['v']) && is_string($query['v'])) {
            return $query['v'];
        }

        $splitPathPiece = fn (string $piece): string => explode('&', $piece)[0];

        /** @var Collection<int, string> $pathPieces */
        $pathPieces = collect(explode('/', $uri->getPath()))->filter()->values();

        if ($pathPieces->count() == 2 && collect(['watch', 'v', 'embed', 'e', 'shorts', 'live'])->contains((string) $pathPieces->first())) {
            return $splitPathPiece((string) $pathPieces[1]);
        }

        if ($pathPieces->first() == 'oembed' && isset($query['url']) && is_string($query['url'])) {
            return $this->getIdFromUrl($query['url']);
        }

        if ($pathPieces->first() == 'attribution_link' && isset($query['u']) && is_string($query['u'])) {
            return $this->getIdFromUrl('https://youtube.com'.$query['u']);
        }

        if ($pathPieces->count() == 1) {
            return $splitPathPiece((string) $pathPieces->first());
        }

        throw new \RuntimeException("Cannot parse URL: $url");
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            return $this->ytDlp->downloadAudio($clipUrl);
        } catch (MembersOnlyContentException $e) {
            throw new ContentUnavailableException;
        } catch (\Exception $e) {
            throw new PlatformException(PlatformType::YouTube, PlatformOperation::Download, $e);
        }
    }

    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        $videoMetadata = $this->youTubeData->getAllVideoMetadataForChannel(
            channelId: $this->getChannelIdFromSourceUrl($sourceUrl),
            publishedAfter: $publicationTime,
        );

        return collect($videoMetadata)->map($this->convertVideoMetadataToClipMetadata(...))->all();
    }

    private function sourceUrlHasChannelId(string $sourceUrl): bool
    {
        return str_contains($sourceUrl, '/channel/');
    }

    private function getLastPathPiece(string $url): string
    {
        return (string) collect(explode('/', Uri::new($url)->getPath()))->last();
    }

    private function convertChannelMetadataToSourceMetadata(ChannelMetadata $channel): SourceMetadata
    {
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
