<?php

namespace App\Actions;

use App\YoutubeDownloader\Metadata;

class StoredAudioResult
{
    public function __construct(
        public readonly string $path,
        public readonly Metadata $metadata,
    ) {}
}
