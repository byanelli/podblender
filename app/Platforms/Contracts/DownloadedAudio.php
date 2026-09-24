<?php

namespace App\Platforms\Contracts;

use App\Apis\Tts\Usage;

readonly class DownloadedAudio
{
    public function __construct(
        // A temporary file, which the caller deletes.
        public string $path,
        // Set when the audio was narrated from text.
        public ?Usage $ttsUsage = null,
    ) {}
}
