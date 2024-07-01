<?php

namespace App\Apis\YouTubeData;

readonly class VideoMetadata
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public ChannelMetadata $channel,
    ) {}
}
