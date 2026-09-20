<?php

namespace App\Apis\YouTubeData\Contracts;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\PlaylistMetadata;
use App\Apis\YouTubeData\VideoMetadata;
use DateTimeInterface;

interface Client
{
    /**
     * @return array<int, mixed>
     */
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

    /**
     * Every video in a playlist published on or after $publishedAfter, in the
     * playlist's own order.
     *
     * playlistItems accepts publishedAfter and ignores it, so the cutoff is
     * applied client-side. A channel's uploads playlist is ordered newest
     * first, so paging stops at the cutoff; any other playlist is paged in
     * full and filtered.
     *
     * @return array<int, VideoMetadata>
     */
    public function getAllVideoMetadataForPlaylist(string $playlistId, ?DateTimeInterface $publishedAfter = null): array;

    public function getPlaylistMetadata(string $playlistId): PlaylistMetadata;
}
