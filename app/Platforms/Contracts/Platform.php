<?php

namespace App\Platforms\Contracts;

use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;

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
     * @throws ContentUnavailableException
     */
    public function downloadAudio(string $clipUrl): string;

    /**
     * @return array<int, ClipMetadata>
     */
    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array;
}
