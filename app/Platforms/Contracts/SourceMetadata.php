<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

readonly class SourceMetadata implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $name,
        public string $canonicalUrl,
    ) {}
}
