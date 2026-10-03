<?php

namespace App\Http\Responses;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One chapter in {@see ChaptersResponse}.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class PodcastChapter implements Arrayable
{
    use IsArrayable;

    public function __construct(
        /** Seconds from the start of the audio. */
        public int $startTime,
        public string $title,
    ) {}
}
