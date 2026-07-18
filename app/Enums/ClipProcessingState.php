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

    // The download failed after exhausting its retries. Unlike Unavailable, which is the platform telling us the
    // content is gone for good, this is a clip we could still download later; it just isn't finished processing now.
    case Failed = 3;
}
