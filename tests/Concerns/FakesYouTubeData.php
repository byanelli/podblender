<?php

namespace Tests\Concerns;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Contracts\Client;
use App\Apis\YouTubeData\PlaylistMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use DateTimeInterface;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesYouTubeData
{
    /**
     * @param  array<int, string>  $videoIdsForChannel
     * @param  array<int, VideoMetadata>  $playlistVideos
     */
    protected function fakeYouTubeData(
        array $videoIdsForChannel = [],
        ?ChannelMetadata $channelMetadata = null,
        ?VideoMetadata $videoMetadata = null,
        array $playlistVideos = [],
        ?PlaylistMetadata $playlistMetadata = null,
    ) {
        $this->app->bind(Client::class, fn () => new readonly class($videoIdsForChannel, $channelMetadata, $videoMetadata, $playlistVideos, $playlistMetadata) implements Client
        {
            /**
             * @param  array<int, string>  $videoIdsForChannel
             * @param  array<int, VideoMetadata>  $playlistVideos
             */
            public function __construct(
                private array $videoIdsForChannel,
                private ?ChannelMetadata $channelMetadata,
                private ?VideoMetadata $videoMetadata,
                private array $playlistVideos,
                private ?PlaylistMetadata $playlistMetadata,
            ) {}

            public function getVideoIdsForChannel(
                string $channelId,
                ?DateTimeInterface $publishedAfter = null,
                ?int $limit = null
            ): array {
                return $this->videoIdsForChannel;
            }

            public function getChannelMetadataForHandle(string $channelHandle): ChannelMetadata
            {
                return $this->channelMetadata;
            }

            public function getChannelMetadataForId(string $channelId): ChannelMetadata
            {
                return $this->channelMetadata;
            }

            public function getVideoMetadata(string $videoId): VideoMetadata
            {
                return $this->videoMetadata;
            }

            public function getAllVideoMetadataForChannel(string $channelId, ?DateTimeInterface $publishedAfter = null): array
            {
                return $this->playlistVideos;
            }

            /**
             * Applies the date cutoff the real client applies as it pages, so a
             * test using this fake sees the same filtering the API path does.
             */
            public function getAllVideoMetadataForPlaylist(string $playlistId, ?DateTimeInterface $publishedAfter = null): array
            {
                return collect($this->playlistVideos)
                    ->filter(fn (VideoMetadata $video) => is_null($publishedAfter) || $video->publishedAt >= $publishedAfter)
                    ->values()
                    ->all();
            }

            public function getPlaylistMetadata(string $playlistId): PlaylistMetadata
            {
                return $this->playlistMetadata;
            }
        });
    }
}
