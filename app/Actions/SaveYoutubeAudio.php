<?php

namespace App\Actions;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use Illuminate\Support\Str;
use Spatie\Regex\Regex;

class SaveYoutubeAudio
{
    public function __construct(
        public readonly DownloadAndStoreYoutubeAudio $downloadAndStore
    ) {}

    /**
     * @throws \Exception
     *
     */
    public function __invoke(string $url): YoutubeVideo {
        $platformId = $this->getIdFromUrl($url) ?? throw new \Exception("Invalid URL: $url");

        /** @var YoutubeVideo $existingVideo */
        $existingVideo = YoutubeVideo::query()
            ->where(YoutubeVideo::COL_PLATFORM_ID, $platformId)
            ->first();

        if ($existingVideo != null) {
            return $existingVideo;
        }

        $result = ($this->downloadAndStore)($url);

        $channel = YoutubeChannel::firstOrCreate([YoutubeChannel::COL_PLATFORM_ID => $result->metadata->channel_id], [
            YoutubeChannel::COL_PLATFORM_ID => $result->metadata->channel_id,
            YoutubeChannel::COL_NAME        => $result->metadata->channel,
        ]);

        /** @var YoutubeVideo $video */
        $video = YoutubeVideo::create([
            YoutubeVideo::COL_PLATFORM_ID        => $result->metadata->id,
            YoutubeVideo::COL_YOUTUBE_CHANNEL_ID => $channel->id,
            YoutubeVideo::COL_TITLE              => Str::limit($result->metadata->title, 500 - 3),
            YoutubeVideo::COL_DESCRIPTION        => Str::limit($result->metadata->description, 1000 - 3),
            YoutubeVideo::COL_DURATION           => $result->metadata->duration,
            YoutubeVideo::COL_STORAGE_PATH       => $result->path,
        ]);

        return $video;
    }

    function getIdFromUrl(string $url): ?string {
        if (($result = Regex::match('/v=(\\w+)/', $url))->hasMatch()) {
            return $result->group(1);
        }

        if (($result = Regex::match('/youtu.be\\/(\\w+)/', $url))->hasMatch()) {
            return $result->group(1);
        }

        return null;
    }
}
