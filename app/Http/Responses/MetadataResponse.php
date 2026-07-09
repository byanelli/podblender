<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use BYanelli\Roma\Response\Response;
use Illuminate\Support\Carbon;

class MetadataResponse extends Response
{
    public function __construct(
        public ClipMetadataResponse $metadata,
        public PlatformTypeResponse $platformType,
    ) {}

    public static function fromDomain(ClipMetadata $metadata, PlatformType $platformType): self
    {
        return new self(
            metadata: new ClipMetadataResponse(
                title: $metadata->title,
                description: $metadata->description,
                canonicalUrl: $metadata->canonicalUrl,
                publishedAt: Carbon::instance($metadata->publishedAt)->utc(),
                source: new SourceMetadataResponse(
                    name: $metadata->source->name,
                    canonicalUrl: $metadata->source->canonicalUrl,
                ),
            ),
            platformType: PlatformTypeResponse::fromEnum($platformType),
        );
    }
}
