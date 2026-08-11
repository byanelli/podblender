<?php

namespace App\Actions;

use App\Enums\PlatformType;
use App\Models\AudioSource;
use App\Platforms\Contracts\SourceMetadata;

readonly class FindOrCreateAudioSource
{
    public function __invoke(
        PlatformType $platformType,
        SourceMetadata $metadata,
    ): AudioSource {
        return AudioSource::query()->firstOrCreate(
            [
                'platform_type' => $platformType,
                'platform_url'  => $metadata->canonicalUrl,
            ],
            [
                'platform_type' => $platformType,
                'platform_url'  => $metadata->canonicalUrl,
                'name'          => $metadata->name,
                'type'          => $metadata->type,
                'author_name'   => $metadata->authorName,
            ]
        );
    }
}
