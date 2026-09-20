<?php

namespace App\Platforms;

use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Exceptions\PlatformNotSubscribableException;
use Illuminate\Contracts\Container\Container;
use League\Uri\Uri;

final class Platforms
{
    use FixesUrls;

    /**
     * The hosts treated as YouTube. Also used by YouTube::getIdFromUrl.
     */
    public const array YOUTUBE_HOSTS = [
        'youtube.com',
        'm.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
    ];

    /**
     * The class for each platform type. for() resolves it from the container on demand, so a request constructs only
     * the platform it uses.
     *
     * @var array<int, class-string<Platform>>
     */
    private const array PLATFORMS = [
        PlatformType::YouTube->value => YouTube::class,
        PlatformType::Web->value     => Web::class,
        PlatformType::Rss->value     => Rss::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function forUrl(string $url): Platform
    {
        return $this->for($this->typeForUrl($url));
    }

    public function for(PlatformType $type): Platform
    {
        return $this->container->make(self::PLATFORMS[$type->value]);
    }

    /**
     * @throws PlatformNotSubscribableException
     */
    public function subscribableFor(PlatformType $type): SubscribablePlatform
    {
        $platform = $this->for($type);

        if (! $platform instanceof SubscribablePlatform) {
            throw new PlatformNotSubscribableException($type);
        }

        return $platform;
    }

    public function typeForUrl(string $url): PlatformType
    {
        $host = Uri::new($this->fixUrlSchemeAndHost($url))->getHost();

        return in_array($host, self::YOUTUBE_HOSTS) ? PlatformType::YouTube : PlatformType::Web;
    }

    /**
     * The platform for a subscription URL. typeForUrl() classifies a clip URL,
     * where a non-YouTube URL is a web article. A web page can't be polled,
     * so a non-YouTube subscription is an RSS/Atom feed, given either as the
     * feed URL or as a page with an autodiscovery link.
     */
    public function subscribableTypeForUrl(string $url): PlatformType
    {
        return $this->typeForUrl($url) === PlatformType::YouTube
            ? PlatformType::YouTube
            : PlatformType::Rss;
    }
}
