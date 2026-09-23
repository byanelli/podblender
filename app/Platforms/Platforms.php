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
     * The hosts treated as SoundCloud. on.soundcloud.com serves the short links from the app's share sheet.
     */
    public const array SOUNDCLOUD_HOSTS = [
        'soundcloud.com',
        'm.soundcloud.com',
        'on.soundcloud.com',
    ];

    /**
     * The hosts of each platform that is identified by its URL. Any other URL is a web article, or an RSS feed when
     * subscribing.
     *
     * @var array<int, array<int, string>>
     */
    private const array HOSTS = [
        PlatformType::YouTube->value    => self::YOUTUBE_HOSTS,
        PlatformType::SoundCloud->value => self::SOUNDCLOUD_HOSTS,
    ];

    /**
     * The class for each platform type. for() resolves it from the container on demand, so a request constructs only
     * the platform it uses.
     *
     * @var array<int, class-string<Platform>>
     */
    private const array PLATFORMS = [
        PlatformType::YouTube->value    => YouTube::class,
        PlatformType::Web->value        => Web::class,
        PlatformType::Rss->value        => Rss::class,
        PlatformType::SoundCloud->value => SoundCloud::class,
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

        foreach (self::HOSTS as $type => $hosts) {
            if (in_array($host, $hosts)) {
                return PlatformType::from($type);
            }
        }

        return PlatformType::Web;
    }

    /**
     * The platform for a subscription URL. typeForUrl() classifies a clip URL,
     * where an unrecognized URL is a web article. A web page can't be polled,
     * so an unrecognized subscription is an RSS/Atom feed, given either as the
     * feed URL or as a page with an autodiscovery link.
     */
    public function subscribableTypeForUrl(string $url): PlatformType
    {
        $type = $this->typeForUrl($url);

        return is_subclass_of(self::PLATFORMS[$type->value], SubscribablePlatform::class)
            ? $type
            : PlatformType::Rss;
    }
}
