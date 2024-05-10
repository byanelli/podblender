<?php

namespace App\Actions;

use App\Models\AudioSource;
use App\Models\AudioClip;
use App\YoutubeDownloader\Client;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class SaveYoutubeVideo
{
    public function __construct(
        private readonly Client $youtubeDownloader
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(string $id): AudioClip {
        $metadata = $this->youtubeDownloader->getMetadata($id);

        $storagePath = Uuid::uuid4()->toString();

        /** @var AudioSource $channel */
        $channel = AudioSource::firstOrCreate([AudioSource::COL_PLATFORM_ID => $metadata->channel_id], [
            AudioSource::COL_PLATFORM_ID => $metadata->channel_id,
            AudioSource::COL_NAME        => $metadata->channel,
        ]);

        /** @var AudioClip $clip */
        $clip = AudioClip::create([
            AudioClip::COL_PLATFORM_ID     => $metadata->id,
            AudioClip::COL_AUDIO_SOURCE_ID => $channel->id,
            AudioClip::COL_TITLE           => Str::limit($metadata->title, 500 - 3),
            AudioClip::COL_DESCRIPTION     => Str::limit($metadata->description, 1000 - 3),
            AudioClip::COL_DURATION        => $metadata->duration,
            AudioClip::COL_STORAGE_PATH    => $storagePath,
            AudioClip::COL_GUID            => Uuid::uuid4()->toString(),
            AudioClip::COL_PROCESSING      => true,
            AudioClip::COL_SIZE            => 0,
        ]);

        return $clip;
    }
}
