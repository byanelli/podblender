<?php

namespace App\Http\Responses;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

readonly class SourceMetadataResponse implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $name,
        public string $canonicalUrl,
    ) {}
}
