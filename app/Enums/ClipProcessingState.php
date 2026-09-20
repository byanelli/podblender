<?php

namespace App\Enums;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
enum ClipProcessingState: int implements Arrayable
{
    use IsArrayable;

    case Processing = 0;
    case Processed = 1;
    case Unavailable = 2;

    // The download failed after exhausting its retries. Unavailable means the platform reported the content as
    // permanently gone; a Failed clip might still download later.
    case Failed = 3;
}
