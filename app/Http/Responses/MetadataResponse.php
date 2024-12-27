<?php

namespace App\Http\Responses;

use App\Enums\IsResponsable;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use Illuminate\Contracts\Support\Responsable;

readonly class MetadataResponse implements Responsable
{
    use IsResponsable;

    public function __construct(
        public ClipMetadata $metadata,
        public PlatformType $platformType,
    ) {}
}
