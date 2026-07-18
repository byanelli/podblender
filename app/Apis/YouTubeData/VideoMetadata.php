<?php

namespace App\Apis\YouTubeData;

use DateTimeInterface;

readonly class VideoMetadata
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public DateTimeInterface $publishedAt,
        public ChannelMetadata $channel,
    ) {}
}
