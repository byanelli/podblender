<?php

namespace App\Platforms\Exceptions;

use App\Enums\PlatformType;

/**
 * Thrown on an attempt to subscribe to a platform that isn't a SubscribablePlatform. This is a programming error that a
 * user can't trigger, so the message is written for the log.
 */
class PlatformNotSubscribableException extends \Exception
{
    public function __construct(PlatformType $platformType)
    {
        parent::__construct("The {$platformType->name} platform does not support subscriptions.");
    }
}
