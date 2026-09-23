<?php

namespace App\Enums;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
enum InboundEmailStatus: int implements Arrayable
{
    use IsArrayable;

    case Pending = 0;
    case Added = 1;
    case Failed = 2;
}
