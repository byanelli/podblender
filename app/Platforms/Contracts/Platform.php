<?php

namespace App\Platforms\Contracts;

use App\Platforms\Metadata;

interface Platform
{
    public function getCanonicalUrl(string $url): string;

    public function getMetadata(string $url): Metadata;

    public function downloadAudio(string $url): string;
}
