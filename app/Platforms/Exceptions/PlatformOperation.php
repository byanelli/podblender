<?php

namespace App\Platforms\Exceptions;

/**
 * The operation a PlatformException occurred during. The verb goes in the user-facing message ("Error downloading
 * from...", "Error getting metadata from...").
 */
enum PlatformOperation
{
    case Metadata;
    case Download;

    public function verb(): string
    {
        return match ($this) {
            self::Metadata => 'getting metadata',
            self::Download => 'downloading',
        };
    }
}
