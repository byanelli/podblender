<?php

namespace App\Platforms;

use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use League\Uri\Uri;

readonly class PlatformTypeResolver
{
    use FixesUrls;

    const array YOUTUBE_HOSTS = [
        'youtube.com',
        'm.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
    ];

    public function fromUrl(string $url): PlatformType
    {
        $url = $this->fixUrlSchemeAndHost($url);

        $host = Uri::fromBaseUri($url)->getHost();

        return match (true) {
            (in_array($host, self::YOUTUBE_HOSTS)) => PlatformType::YouTube,
            default => PlatformType::Web,
        };
    }
}
