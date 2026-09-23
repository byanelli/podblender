<?php

namespace App\Platforms\Exceptions;

use App\Apis\YouTubeData\ResourceNotFoundException;
use App\Enums\PlatformType;
use Throwable;

/**
 * Reports a generic message to the user and hides the previous exception's details, which may come from a shell command
 * or an API request on the server.
 *
 * A cause the user can act on, such as a link to something the platform doesn't have, is reported with its own
 * message, which is written for the user.
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
     * Whether the cause's message was written for the user.
     */
    private static function isSafeToReport(Throwable $previous): bool
    {
        return $previous instanceof ResourceNotFoundException
            || $previous instanceof UnusableLinkException;
    }
}
