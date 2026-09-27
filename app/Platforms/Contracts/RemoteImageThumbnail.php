<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Artwork hosted by the platform. The URLs are tried in order, and a URL that
 * returns 404 or a non-image response is skipped.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class RemoteImageThumbnail implements Arrayable, ThumbnailSource
{
    use IsArrayable;

    /**
     * @param  non-empty-list<string>  $urls  Largest first.
     */
    public function __construct(public array $urls) {}
}
