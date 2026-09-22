<?php

namespace App\Apis\YouTubeData;

/**
 * A channel as named in a video or playlist response, which gives only its id
 * and title.
 */
readonly class ChannelReference
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
