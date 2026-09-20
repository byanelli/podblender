<?php

namespace App\Actions;

use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Jobs\DownloadAndStoreThumbnail;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\ClipMetadata;
use App\Support\AudioClipStoragePath;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

readonly class FindOrCreateAudioClip
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(PlatformType $platformType, ClipMetadata $metadata): AudioClip
    {
        // A platform typically has many URL formats for the same content, so match on the canonical URL from the
        // metadata request.
        if ($existing = AudioClip::query()->where('platform_url', $metadata->canonicalUrl)->first()) {
            return $existing;
        }

        /** @var AudioSource $source */
        $source = AudioSource::query()->firstOrCreate(
            [
                'platform_type' => $platformType,
                'platform_url'  => $metadata->source->canonicalUrl,
            ],
            [
                'platform_type' => $platformType,
                'platform_url'  => $metadata->source->canonicalUrl,
                'name'          => $metadata->source->name,
            ]
        );

        $storagePath = AudioClipStoragePath::for($source->name, $metadata->title);

        // A clip in the Processing state is left out of RSS feeds. The download job queued below sets the final state.
        try {
            /** @var AudioClip $clip */
            $clip = AudioClip::query()->create([
                'platform_url'            => $metadata->canonicalUrl,
                'audio_source_id'         => $source->id,
                'title'                   => Str::limit($metadata->title, 500 - 3),
                'description'             => Str::limit($metadata->description, 1000 - 3),
                'published_at'            => $metadata->publishedAt,
                'duration'                => 0,
                'estimated_download_time' => $metadata->estimatedDownloadTime,
                'storage_path'            => $storagePath,
                'guid'                    => Uuid::uuid4()->toString(),
                'processing_state'        => ClipProcessingState::Processing,
                'size'                    => 0,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // The existence check above and this insert aren't atomic, and platform_url is unique. If the clip now
            // exists, a concurrent job created it and queued its download. Otherwise another unique column was violated.
            return AudioClip::query()->where('platform_url', $metadata->canonicalUrl)->first() ?? throw $e;
        }

        $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));

        // A separate job, so a thumbnail failure can't delay or fail the audio download.
        if ($metadata->thumbnail !== null) {
            $this->dispatcher->dispatch(new DownloadAndStoreThumbnail($clip, $metadata->thumbnail));
        }

        return $clip;
    }
}
