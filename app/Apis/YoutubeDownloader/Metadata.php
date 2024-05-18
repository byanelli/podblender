<?php

namespace App\Apis\YoutubeDownloader;

readonly class Metadata
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $channel_id,
        public string $channel,
        public int    $duration,
    ) {}
}
