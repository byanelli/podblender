<?php

namespace App\Apis\YouTubeData;

readonly class ChannelMetadata
{
    public function __construct(
        public string $id,
        public string $name,
        /**
         * The channel's "uploads" playlist, which contains every video it has
         * published. Only present when the request asked for contentDetails.
         */
        public ?string $uploadsPlaylistId = null,
        /**
         * How many videos the channel has published. Only present when the
         * request asked for statistics. Shown to a subscriber before a full
         * backfill.
         */
        public ?int $videoCount = null,
    ) {}

    /**
     * A channel's uploads playlist id is the channel id with the "UC" prefix
     * replaced by "UU" (documented by YouTube). playlistItems lists the whole
     * playlist, whereas search.list stops returning pages after ~500 results.
     */
    public function uploadsPlaylistId(): string
    {
        if ($this->uploadsPlaylistId !== null) {
            return $this->uploadsPlaylistId;
        }

        return str_starts_with($this->id, 'UC')
            ? 'UU'.substr($this->id, 2)
            : throw new \RuntimeException("Can't derive an uploads playlist for channel: $this->id");
    }
}
