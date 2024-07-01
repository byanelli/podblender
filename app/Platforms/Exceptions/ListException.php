<?php

namespace App\Platforms\Exceptions;

use App\Enums\PlatformType;

/**
 * If an error occurs while listing clips, we want to report a generic message to the user while hiding details of the
 * previous exception, since it may have occurred while running a shell command or API request  on the server.
 */
class ListException extends \Exception
{
    public function __construct(PlatformType $platformType, \Throwable $previous)
    {
        parent::__construct(
            message: "Error listing clips from {$platformType->name}",
            previous: $previous,
        );
    }
}
