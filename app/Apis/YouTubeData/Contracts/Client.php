<?php

namespace App\Apis\YouTubeData\Contracts;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use DateTimeInterface;

interface Client
{
    public function getVideoIdsForChannel(
        string $channelId,
        ?DateTimeInterface $publishedAfter = null,
        ?int $limit = null,
    ): array;

    public function getChannelMetadataForHandle(string $channelHandle): ChannelMetadata;

    public function getChannelMetadataForId(string $channelId): ChannelMetadata;

    public function getVideoMetadata(string $videoId): VideoMetadata;

    /**
     * @return array<int, VideoMetadata>
     */
    public function getAllVideoMetadataForChannel(string $channelId, ?DateTimeInterface $publishedAfter = null): array;
}
