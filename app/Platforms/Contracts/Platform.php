<?php

namespace App\Platforms\Contracts;

use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use App\Platforms\Exceptions\ListException;

interface Platform
{
    /**
     * @throws MetadataException
     */
    public function getClipMetadata(string $clipUrl): ClipMetadata;

    /**
     * @throws MetadataException
     */
    public function getSourceMetadata(string $sourceUrl): SourceMetadata;

    /**
     * @throws DownloadException
     */
    public function downloadAudio(string $clipUrl): string;

    /**
     * @throws ListException
     */
    public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array;
}
