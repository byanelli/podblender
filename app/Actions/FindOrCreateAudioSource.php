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
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_URL => $metadata->canonicalUrl,
            ],
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_URL => $metadata->canonicalUrl,
                AudioSource::COL_NAME => $metadata->name,
                AudioSource::COL_KIND => $metadata->kind,
                AudioSource::COL_AUTHOR_NAME => $metadata->authorName,
            ]
        );
    }
}
