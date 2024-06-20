<?php

namespace App\Platforms\Exceptions;

use App\Enums\PlatformType;
use Throwable;

/**
 * If an error occurs while downloading, we want to report a generic message to the user while hiding details of the
 * previous exception, since it may have occurred while running a shell command on the server.
 */
class DownloadException extends \Exception
{
    public function __construct(PlatformType $platformType, Throwable $previous)
    {
        parent::__construct(
            message: "Error downloading from {$platformType->name}",
            previous: $previous,
        );
    }
}
