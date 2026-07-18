<?php

namespace App\Platforms\Exceptions;

use App\Enums\PlatformType;

/**
 * Thrown when something tries to subscribe to a platform that can't list its own clips (see SubscribablePlatform).
 * Reaching this is a programming error rather than something a user can trigger, so the message names the platform
 * plainly for the log.
 */
class PlatformNotSubscribableException extends \Exception
{
    public function __construct(PlatformType $platformType)
    {
        parent::__construct("The {$platformType->name} platform does not support subscriptions.");
    }
}
