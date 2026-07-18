<?php

namespace App\Platforms\Exceptions;

/**
 * The operation a PlatformException occurred during. The verb is what distinguishes one user-facing message from
 * another ("Error downloading from..." vs "Error getting metadata from...").
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
