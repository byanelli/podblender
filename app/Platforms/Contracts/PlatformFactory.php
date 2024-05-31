<?php

namespace App\Platforms\Contracts;

use App\Enums\PlatformType;

interface PlatformFactory
{
    public function make(PlatformType $platformType): Platform;
}
