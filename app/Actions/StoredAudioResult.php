<?php

namespace App\Actions;

use App\YoutubeDownloader\Metadata;

readonly class StoredAudioResult
{
    public function __construct(
        public string $path,
        public Metadata $metadata,
    ) {}
}
