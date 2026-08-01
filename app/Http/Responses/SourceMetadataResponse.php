<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use App\Platforms\Contracts\SourceMetadata;
use BYanelli\Roma\Response\Response;

class SourceMetadataResponse extends Response
{
    public function __construct(
        public SourceMetadata $metadata,
        public PlatformType $platformType,
    ) {}
}
