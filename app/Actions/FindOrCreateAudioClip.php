<?php

namespace App\Actions;

use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\Exceptions\MetadataException;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

readonly class FindOrCreateAudioClip
{
    public function __construct(private PlatformFactory $platformFactory) {}

    /**
     * @throws MetadataException
     */
    public function __invoke(PlatformType $platformType, string $url): AudioClip
    {
        $platform = $this->platformFactory->make($platformType);

        // Download the metadata from the platform.
        $metadata = $platform->getClipMetadata($url);

        // If a clip already exists for this URL, return it instead of creating a new one. NOTE: A platform will
        // typically have many URL formats pointing to the same content. Here we use the canonical form of the URL,
        // retrieved during the metadata request, to avoid duplication.
        /** @var AudioClip $existing */
        if ($existing = AudioClip::query()->where(AudioClip::COL_PLATFORM_URL, $metadata->canonicalUrl)->first()) {
            return $existing;
        }

        $storagePath = Uuid::uuid4()->toString();

        // Find an existing audio source in the database or create one from the metadata.
        /** @var AudioSource $source */
        $source = AudioSource::query()->firstOrCreate(
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_ID => $metadata->source->canonicalUrl,
            ],
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_ID => $metadata->source->canonicalUrl,
                AudioSource::COL_NAME => $metadata->source->name,
            ]
        );

        // Create the audio clip from the metadata with processing=true. While this column is true, the clip will not
        // show up in RSS feeds. A queued job will be dispatched to download the audio and set processing=false.
        /** @var AudioClip $clip */
        $clip = AudioClip::query()->create([
            AudioClip::COL_PLATFORM_URL => $metadata->canonicalUrl,
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_TITLE => Str::limit($metadata->title, 500 - 3),
            AudioClip::COL_DESCRIPTION => Str::limit($metadata->description, 1000 - 3),
            AudioClip::COL_DURATION => 0,
            AudioClip::COL_STORAGE_PATH => $storagePath,
            AudioClip::COL_GUID => Uuid::uuid4()->toString(),
            AudioClip::COL_PROCESSING => true,
            AudioClip::COL_SIZE => 0,
        ]);

        return $clip;
    }
}
