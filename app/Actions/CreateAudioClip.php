<?php

namespace App\Actions;

use App\Enums\PlatformType;
use App\Models\AudioSource;
use App\Models\AudioClip;
use App\Platforms\Contracts\PlatformFactory;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

readonly class CreateAudioClip
{
    public function __construct(private PlatformFactory $platformFactory) {}

    /**
     * @throws \Exception
     */
    public function __invoke(PlatformType $platformType, string $url): AudioClip {
        $platform = $this->platformFactory->make($platformType);

        // Download the metadata from the platform.
        $metadata = $platform->getMetadata($url);

        $storagePath = Uuid::uuid4()->toString();

        // Find an existing audio source in the database or create one from the metadata.
        /** @var AudioSource $source */
        $source = AudioSource::query()->firstOrCreate(
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_ID   => $metadata->sourceId,
            ],
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_ID   => $metadata->sourceId,
                AudioSource::COL_NAME          => $metadata->sourceName,
            ]
        );

        // Create the audio clip from the metadata with processing=true. While this column is true, the clip will not
        // show up in RSS feeds. A queued job will be dispatched to download the audio and set processing=false.
        /** @var AudioClip $clip */
        $clip = AudioClip::query()->create([
            AudioClip::COL_PLATFORM_URL    => $url,
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_TITLE           => Str::limit($metadata->title, 500 - 3),
            AudioClip::COL_DESCRIPTION     => Str::limit($metadata->description, 1000 - 3),
            AudioClip::COL_DURATION        => 0,
            AudioClip::COL_STORAGE_PATH    => $storagePath,
            AudioClip::COL_GUID            => Uuid::uuid4()->toString(),
            AudioClip::COL_PROCESSING      => true,
            AudioClip::COL_SIZE            => 0,
        ]);

        return $clip;
    }
}
