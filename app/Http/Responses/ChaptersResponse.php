<?php

namespace App\Http\Responses;

use App\Platforms\Contracts\Chapter;
use BYanelli\Roma\Response\Attributes\Header;
use BYanelli\Roma\Response\Response;

/**
 * A clip's chapters in the Podcasting 2.0 JSON chapters format, which the RSS feed links to with <podcast:chapters>.
 * https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/examples/chapters/jsonChapters.md
 */
class ChaptersResponse extends Response
{
    /**
     * @param  list<PodcastChapter>  $chapters
     */
    public function __construct(
        public array $chapters,
        public string $version = '1.2.0',
        #[Header('Content-Type')]
        public string $contentType = 'application/json+chapters',
    ) {}

    /**
     * @param  list<Chapter>  $chapters
     */
    public static function for(array $chapters): self
    {
        return new self(array_map(fn (Chapter $c) => new PodcastChapter($c->startSeconds, $c->title), $chapters));
    }
}
