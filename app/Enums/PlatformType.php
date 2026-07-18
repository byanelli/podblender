<?php

namespace App\Enums;

use BYanelli\Roma\Response\IsArrayable;
use BYanelli\Roma\TypeScript\Attributes\TypeScriptName;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
// Renamed in generated TypeScript to avoid clashing with the hand-written
// PlatformType model type in resources/js/types.ts.
#[TypeScriptName('PlatformTypeEnum')]
enum PlatformType: int implements Arrayable
{
    use IsArrayable;

    case YouTube = 1;
    case Web = 2;
}
