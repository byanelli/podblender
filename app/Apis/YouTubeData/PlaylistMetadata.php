<?php

namespace App\Apis\YouTubeData;

readonly class PlaylistMetadata
{
    public function __construct(
        public string $id,
        public string $title,
        /**
         * The playlist's owner channel. Used as the feed's author, because a
         * playlist's title names a collection.
         */
        public ChannelReference $channel,
        /** How many videos the playlist contains, when the API reports it. */
        public ?int $itemCount = null,
    ) {}
}
