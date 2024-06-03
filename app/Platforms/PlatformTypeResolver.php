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

    const array SOUNDCLOUD_HOSTS = [
        'soundcloud.com',
        'on.soundcloud.com',
    ];

    const array TWITCH_HOSTS = [
        'twitch.tv',
        'twitch.com',
    ];

    public function fromUrl(string $url): PlatformType {
        $url = $this->fixUrlSchemeAndHost($url);

        $host = Uri::fromBaseUri($url)->getHost();

        return match (true) {
            (in_array($host, self::YOUTUBE_HOSTS)) => PlatformType::YouTube,
            (in_array($host, self::SOUNDCLOUD_HOSTS)) => PlatformType::SoundCloud,
            (in_array($host, self::TWITCH_HOSTS)) => PlatformType::Twitch,
            default => PlatformType::Web,
        };
    }
}
