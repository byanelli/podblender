<?php

namespace App\Actions;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
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
    public function __invoke(string $id): YoutubeVideo {
        $metadata = $this->youtubeDownloader->getMetadata($id);

        $storagePath = Uuid::uuid4()->toString();

        /** @var YoutubeChannel $channel */
        $channel = YoutubeChannel::firstOrCreate([YoutubeChannel::COL_PLATFORM_ID => $metadata->channel_id], [
            YoutubeChannel::COL_PLATFORM_ID => $metadata->channel_id,
            YoutubeChannel::COL_NAME        => $metadata->channel,
        ]);

        /** @var YoutubeVideo $video */
        $video = YoutubeVideo::create([
            YoutubeVideo::COL_PLATFORM_ID        => $metadata->id,
            YoutubeVideo::COL_YOUTUBE_CHANNEL_ID => $channel->id,
            YoutubeVideo::COL_TITLE              => Str::limit($metadata->title, 500 - 3),
            YoutubeVideo::COL_DESCRIPTION        => Str::limit($metadata->description, 1000 - 3),
            YoutubeVideo::COL_DURATION           => $metadata->duration,
            YoutubeVideo::COL_STORAGE_PATH       => $storagePath,
            YoutubeVideo::COL_PROCESSING         => true,
        ]);

        return $video;
    }
}
