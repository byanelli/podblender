<?php

namespace App\Platforms\Exceptions;

use App\Apis\YouTubeData\ResourceNotFoundException;
use App\Enums\PlatformType;
use Throwable;

/**
 * If an error occurs while talking to a platform, we want to report a generic message to the user while hiding details
 * of the previous exception, since it may have occurred while running a shell command or API request on the server.
 *
 * The exception to that is a cause the user can act on — a link that names nothing the platform has. Saying so is more
 * use than "Error fetching metadata from YouTube", and those messages are written for the user rather than being
 * whatever a subprocess printed.
 */
class PlatformException extends \Exception
{
    public function __construct(PlatformType $platformType, PlatformOperation $operation, Throwable $previous)
    {
        parent::__construct(
            message: self::isSafeToReport($previous)
                ? $previous->getMessage()
                : "Error {$operation->verb()} from {$platformType->name}",
            previous: $previous,
        );
    }

    /**
     * Is this a cause whose message was written to be read by whoever pasted the link?
     */
    private static function isSafeToReport(Throwable $previous): bool
    {
        return $previous instanceof ResourceNotFoundException;
    }
}
