<?php

namespace App\Apis\YouTubeData;

/**
 * A channel as returned by channels.list.
 */
readonly class ChannelMetadata
{
    public function __construct(
        public string $id,
        public string $name,
        /** The playlist that contains every video the channel has published. */
        public string $uploadsPlaylistId,
        /**
         * How many videos the channel has published, or null when the API
         * omits it. Shown to a subscriber before a full backfill.
         */
        public ?int $videoCount,
    ) {}

    /**
     * A channel's uploads playlist id is its channel id with the "UC" prefix
     * replaced by "UU". This is a convention YouTube doesn't document, but
     * many clients rely on it. playlistItems lists the whole playlist, whereas
     * search.list stops returning pages after ~500 results.
     */
    public static function uploadsPlaylistIdFor(string $channelId): string
    {
        return str_starts_with($channelId, 'UC')
            ? 'UU'.substr($channelId, 2)
            : throw new \RuntimeException("Can't derive an uploads playlist for channel: $channelId");
    }
}
