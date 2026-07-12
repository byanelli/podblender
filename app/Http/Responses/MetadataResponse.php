<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use BYanelli\Roma\Response\Response;

class MetadataResponse extends Response
{
    public function __construct(
        public ClipMetadata $metadata,
        public PlatformType $platformType,
    ) {}
}
