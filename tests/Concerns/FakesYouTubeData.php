<?php

namespace Tests\Concerns;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Contracts\Client;
use App\Apis\YouTubeData\VideoMetadata;
use DateTimeInterface;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesYouTubeData
{
    protected function fakeYouTubeData(
        array $videoIdsForChannel = [],
        ?ChannelMetadata $channelMetadata = null,
        ?VideoMetadata $videoMetadata = null,
    ) {
        $this->app->bind(Client::class, fn () => new readonly class($videoIdsForChannel, $channelMetadata, $videoMetadata) implements Client
        {
            public function __construct(
                private array $videoIdsForChannel,
                private ?ChannelMetadata $channelMetadata,
                private ?VideoMetadata $videoMetadata,
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
                // TODO: Implement getAllVideoMetadataForChannel() method.
            }
        });
    }
}
