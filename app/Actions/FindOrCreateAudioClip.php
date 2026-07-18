<?php

namespace App\Actions;

use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\ClipMetadata;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

readonly class FindOrCreateAudioClip
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(PlatformType $platformType, ClipMetadata $metadata): AudioClip
    {
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
                AudioSource::COL_PLATFORM_URL => $metadata->source->canonicalUrl,
            ],
            [
                AudioSource::COL_PLATFORM_TYPE => $platformType,
                AudioSource::COL_PLATFORM_URL => $metadata->source->canonicalUrl,
                AudioSource::COL_NAME => $metadata->source->name,
            ]
        );

        // Create the audio clip from the metadata with processing=true. While this column is true, the clip will not
        // show up in RSS feeds. A queued job will be dispatched to download the audio and set processing=false.
        try {
            /** @var AudioClip $clip */
            $clip = AudioClip::query()->create([
                AudioClip::COL_PLATFORM_URL => $metadata->canonicalUrl,
                AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
                AudioClip::COL_TITLE => Str::limit($metadata->title, 500 - 3),
                AudioClip::COL_DESCRIPTION => Str::limit($metadata->description, 1000 - 3),
                AudioClip::COL_PUBLISHED_AT => $metadata->publishedAt,
                AudioClip::COL_DURATION => 0,
                AudioClip::COL_STORAGE_PATH => $storagePath,
                AudioClip::COL_GUID => Uuid::uuid4()->toString(),
                AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processing,
                AudioClip::COL_SIZE => 0,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // The existence check above and this insert aren't atomic, and platform_url is unique. Two concurrent
            // updates of the same subscription can both pass the check and race to create the same clip; whichever
            // insert loses lands here. The other job already created the clip and dispatched its download, so return
            // the winner rather than failing the whole update.
            return AudioClip::query()->where(AudioClip::COL_PLATFORM_URL, $metadata->canonicalUrl)->firstOrFail();
        }

        // Queue a job to download the clip.
        $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));

        return $clip;
    }
}
