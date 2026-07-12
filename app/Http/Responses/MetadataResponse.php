<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use BYanelli\Roma\Response\Response;

class MetadataResponse extends Response
{
    public function __construct(
        public ClipMetadata $metadata,
        public PlatformTypeResponse $platformType,
    ) {}

    public static function fromDomain(ClipMetadata $metadata, PlatformType $platformType): self
    {
        return new self(
            metadata: $metadata,
            platformType: PlatformTypeResponse::fromEnum($platformType),
        );
    }
}
