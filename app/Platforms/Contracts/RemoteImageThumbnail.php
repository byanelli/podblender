<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Artwork the platform already hosts: fetch this URL and use what comes back.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class RemoteImageThumbnail implements Arrayable, ThumbnailSource
{
    use IsArrayable;

    public function __construct(public string $url) {}
}
