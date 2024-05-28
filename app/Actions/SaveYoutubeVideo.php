<?php

namespace App\Actions;

use App\Models\AudioSource;
use App\Models\AudioClip;
use App\Apis\YtDlp\Client;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class SaveYoutubeVideo
{
    public function __construct(private readonly Client $youtubeDownloader) {}

    /**
     * @throws \Exception
     */
    public function __invoke(string $id): AudioClip {
        $metadata = $this->youtubeDownloader->getYoutubeMetadata($id);

        $storagePath = Uuid::uuid4()->toString();

        // Find an existing channel in the database or create one from the metadata.
        /** @var AudioSource $channel */
        $channel = AudioSource::firstOrCreate([AudioSource::COL_PLATFORM_ID => $metadata->channel_id], [
            AudioSource::COL_PLATFORM_ID => $metadata->channel_id,
            AudioSource::COL_NAME        => $metadata->channel,
        ]);

        // Create the audio clip from the metadata with processing=true. While this column is true, the clip will not
        // show up in RSS feeds. A queued job will be dispatched to download the audio and set processing=false.
        /** @var AudioClip $clip */
        $clip = AudioClip::create([
            AudioClip::COL_PLATFORM_ID     => $metadata->id,
            AudioClip::COL_AUDIO_SOURCE_ID => $channel->id,
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
