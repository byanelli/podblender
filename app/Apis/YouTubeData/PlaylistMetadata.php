<?php

namespace App\Apis\YouTubeData;

readonly class PlaylistMetadata
{
    public function __construct(
        public string $id,
        public string $title,
        /**
         * The channel that owns the playlist. This is who the feed is "by":
         * a playlist's own title is a collection name, not an author, so it
         * can't be used for authorship the way a channel's name can.
         */
        public ChannelMetadata $channel,
        /** How many videos the playlist holds, when the API reports it. */
        public ?int $itemCount = null,
    ) {}
}
