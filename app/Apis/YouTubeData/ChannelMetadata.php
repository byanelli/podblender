<?php

namespace App\Apis\YouTubeData;

readonly class ChannelMetadata
{
    public function __construct(
        public string $id,
        public string $name,
        /**
         * The channel's "uploads" playlist, which holds every video it has
         * published. Only present when the response asked for contentDetails.
         */
        public ?string $uploadsPlaylistId = null,
    ) {}

    /**
     * A channel's uploads playlist is its own id with the "UC" channel prefix
     * swapped for "UU". YouTube documents this, and it means a channel can be
     * listed exhaustively through playlistItems — unlike search.list, which
     * stops returning pages after ~500 results.
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
