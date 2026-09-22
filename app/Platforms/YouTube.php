<?php

namespace App\Platforms;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Contracts\Client as YouTubeDataClient;
use App\Apis\YouTubeData\PlaylistMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use App\Apis\YtDlp\Client as YtDlpClient;
use App\Apis\YtDlp\MembersOnlyContentException;
use App\Concerns\FixesUrls;
use App\Enums\AudioSourceType;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\RemoteImageThumbnail;
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
            source: $this->channelSourceMetadata($video->channel->id, $video->channel->name),
            estimatedDownloadTime: $this->estimateDownloadTime($video),
            thumbnail: $video->thumbnailUrl === null
                ? null
                : new RemoteImageThumbnail($video->thumbnailUrl),
        );
    }

    /**
     * A conservative estimate of one download's wall-clock time, in seconds.
     * yt-dlp downloads the audio track (~160 kbps) and throttles its requests,
     * so this assumes 2 Mbps plus fixed overhead. Null when the API reported
     * no duration.
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

    /**
     * Lists a source's clips through its playlist. A channel is listed through
     * its "uploads" playlist, so channels and playlists share one code path.
     *
     * search.list stops paging after roughly 500 results. For an 864-video
     * channel it returned 303 videos for 700 quota units; the uploads playlist
     * returned all 864 for 18.
     */
    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        $videoMetadata = $this->youTubeData->getAllVideoMetadataForPlaylist(
            playlistId: $this->getPlaylistIdFromSourceUrl($sourceUrl),
            publishedAfter: $publicationTime,
        );

        return collect($videoMetadata)->map($this->convertVideoMetadataToClipMetadata(...))->all();
    }

    /**
     * The playlist that lists a source's clips: the playlist itself, or a
     * channel's uploads playlist.
     */
    private function getPlaylistIdFromSourceUrl(string $sourceUrl): string
    {
        if ($playlistId = $this->getPlaylistIdFromUrl($sourceUrl)) {
            return $playlistId;
        }

        $channelIdOrHandle = $this->getLastPathPiece($sourceUrl);

        return $this->sourceUrlHasChannelId($sourceUrl)
            ? ChannelMetadata::uploadsPlaylistIdFor($channelIdOrHandle)
            : $this->youTubeData->getChannelMetadataForHandle($channelIdOrHandle)->uploadsPlaylistId;
    }

    /**
     * The playlist id from /playlist?list=... or from a watch URL with a list=
     * parameter. Null otherwise, in which case the source is a channel.
     */
    private function getPlaylistIdFromUrl(string $url): ?string
    {
        parse_str(Uri::new($this->fixUrlSchemeAndHost($url))->getQuery() ?? '', $query);

        $list = $query['list'] ?? null;

        return is_string($list) && $list !== '' ? $list : null;
    }

    private function sourceUrlHasChannelId(string $sourceUrl): bool
    {
        return str_contains($sourceUrl, '/channel/');
    }

    private function getLastPathPiece(string $url): string
    {
        return (string) collect(explode('/', Uri::new($url)->getPath()))->last();
    }

    private function channelSourceMetadata(string $id, string $name, ?int $videoCount = null): SourceMetadata
    {
        return new SourceMetadata(
            name: $name,
            canonicalUrl: "https://youtube.com/channel/{$id}",
            authorName: $name,
            type: AudioSourceType::Channel,
            clipCount: $videoCount,
        );
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        try {
            if ($playlistId = $this->getPlaylistIdFromUrl($sourceUrl)) {
                return $this->convertPlaylistMetadataToSourceMetadata(
                    $this->youTubeData->getPlaylistMetadata($playlistId)
                );
            }

            $channelIdOrHandle = $this->getLastPathPiece($sourceUrl);

            $channel = $this->sourceUrlHasChannelId($sourceUrl)
                ? $this->youTubeData->getChannelMetadataForId($channelIdOrHandle)
                : $this->youTubeData->getChannelMetadataForHandle($channelIdOrHandle);

            return $this->channelSourceMetadata($channel->id, $channel->name, $channel->videoCount);
        } catch (\Exception $e) {
            throw new PlatformException(PlatformType::YouTube, PlatformOperation::Metadata, $e);
        }
    }

    /**
     * The author is the playlist's channel, because a playlist title names a
     * collection ("Select Lectures").
     */
    private function convertPlaylistMetadataToSourceMetadata(PlaylistMetadata $playlist): SourceMetadata
    {
        return new SourceMetadata(
            name: $playlist->title,
            canonicalUrl: "https://youtube.com/playlist?list={$playlist->id}",
            type: AudioSourceType::Playlist,
            authorName: $playlist->channel->name,
            clipCount: $playlist->itemCount,
        );
    }

}
