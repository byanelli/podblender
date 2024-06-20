<?php

namespace App\Platforms\Contracts;

use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\Metadata;

interface Platform
{
    public function getCanonicalUrl(string $url): string;

    /**
     * @throws MetadataException
     */
    public function getMetadata(string $url): Metadata;

    /**
     * @throws DownloadException
     */
    public function downloadAudio(string $url): string;
}
