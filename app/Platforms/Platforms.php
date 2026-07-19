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
     * The hosts we treat as YouTube. This is the single authoritative list; YouTube::getIdFromUrl references it too.
     */
    public const array YOUTUBE_HOSTS = [
        'youtube.com',
        'm.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
    ];

    /**
     * Which concrete class serves each platform type. Resolved lazily from the container (see for()) so a request only
     * ever constructs the one platform it actually uses, rather than every platform up front.
     *
     * @var array<int, class-string<Platform>>
     */
    private const array PLATFORMS = [
        PlatformType::YouTube->value => YouTube::class,
        PlatformType::Web->value => Web::class,
        PlatformType::Rss->value => Rss::class,
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
     * The platform a SUBSCRIPTION URL belongs to. Unlike typeForUrl — which
     * classifies a single clip's URL, where non-YouTube means a web article —
     * a subscription to anything that isn't a YouTube channel can only mean an
     * RSS/Atom feed, since a bare web page has nothing to poll. The Rss
     * platform accepts either the feed URL itself or a page that advertises
     * one via autodiscovery.
     */
    public function subscribableTypeForUrl(string $url): PlatformType
    {
        return $this->typeForUrl($url) === PlatformType::YouTube
            ? PlatformType::YouTube
            : PlatformType::Rss;
    }
}
