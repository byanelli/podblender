<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Arrayable;

enum PlatformType: int implements Arrayable
{
    use IsArrayable;

    case YouTube = 1;
    case Web = 2;
    case SoundCloud = 3;
    case Twitch = 4;
}
