<?php

namespace App\Apis\YouTubeData;

use DateTimeInterface;

readonly class VideoMetadata
{
    /**
     * @param  list<string>  $thumbnailUrls  Largest first.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public DateTimeInterface $publishedAt,
        public ChannelReference $channel,
        /** Length of the video in seconds, when the API reports it. */
        public ?int $durationSeconds = null,
        public array $thumbnailUrls = [],
    ) {}
}
