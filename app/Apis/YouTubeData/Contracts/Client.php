<?php

namespace App\Apis\YouTubeData\Contracts;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use DateTimeInterface;

interface Client
{
    public function getVideoIdsForChannel(
        string $channelId,
        ?DateTimeInterface $publishedAfter=null,
        ?int $limit=null,
    ): array;

    public function getChannelMetadataForHandle(string $handle): ChannelMetadata;

    public function getChannelMetadataForId(string $id): ChannelMetadata;

    public function getVideoMetadata(string $id): VideoMetadata;
}
