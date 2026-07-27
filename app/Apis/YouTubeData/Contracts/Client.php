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
     * Every video in a playlist published on or after $publishedAfter, newest
     * first.
     *
     * The API has no server-side date filter here — playlistItems accepts
     * publishedAfter and silently ignores it — so the cutoff is applied as we
     * page. Items come back newest-first, which lets paging stop as soon as it
     * crosses the cutoff rather than reading the whole playlist.
     *
     * @return array<int, VideoMetadata>
     */
    public function getAllVideoMetadataForPlaylist(string $playlistId, ?DateTimeInterface $publishedAfter = null): array;

    public function getPlaylistMetadata(string $playlistId): PlaylistMetadata;
}
