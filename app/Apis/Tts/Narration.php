<?php

namespace App\Apis\Tts;

readonly class Narration
{
    public function __construct(
        // An MP3 file.
        public string $path,
        // Null when the provider doesn't report usage.
        public ?Usage $usage = null,
    ) {}
}
