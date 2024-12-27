<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Arrayable;

enum ClipProcessingState: int implements Arrayable
{
    use IsArrayable;

    case Processing = 0;
    case Processed = 1;
    case Unavailable = 2;
}
