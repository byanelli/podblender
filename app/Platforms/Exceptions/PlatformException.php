<?php

namespace App\Platforms\Exceptions;

use App\Enums\PlatformType;
use Throwable;

/**
 * If an error occurs while talking to a platform, we want to report a generic message to the user while hiding details
 * of the previous exception, since it may have occurred while running a shell command or API request on the server.
 */
class PlatformException extends \Exception
{
    public function __construct(PlatformType $platformType, PlatformOperation $operation, Throwable $previous)
    {
        parent::__construct(
            message: "Error {$operation->verb()} from {$platformType->name}",
            previous: $previous,
        );
    }
}
