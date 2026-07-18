<?php

namespace App\Platforms\Contracts;

use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\PlatformException;

interface Platform
{
    /**
     * @throws PlatformException
     */
    public function getClipMetadata(string $clipUrl): ClipMetadata;

    /**
     * @throws PlatformException
     */
    public function getSourceMetadata(string $sourceUrl): SourceMetadata;

    /**
     * @throws PlatformException
     * @throws ContentUnavailableException
     */
    public function downloadAudio(string $clipUrl): string;
}
