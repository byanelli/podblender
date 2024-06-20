<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use App\Platforms\Metadata;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

readonly class MetadataResponse implements Responsable
{
    public function __construct(
        private Metadata $metadata,
        private PlatformType $platformType,
    ) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'metadata' => $this->metadata,
            'platformType' => $this->platformType->toArray(),
        ]);
    }
}
