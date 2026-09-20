<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Artwork hosted by the platform, fetched from this URL.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class RemoteImageThumbnail implements Arrayable, ThumbnailSource
{
    use IsArrayable;

    public function __construct(public string $url) {}
}
