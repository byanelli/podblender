<?php

namespace App\YoutubeDownloader;

class Metadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $channel_id,
        public readonly string $channel,
        public readonly ?string $uploader_id,
        public readonly ?string $uploader,
        public readonly int $duration,
    ) {}
}
