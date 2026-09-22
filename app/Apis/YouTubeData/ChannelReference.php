<?php

namespace App\Apis\YouTubeData;

/**
 * The channel that a video or playlist response refers to. The response gives
 * only the channel's id and title.
 */
readonly class ChannelReference
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
