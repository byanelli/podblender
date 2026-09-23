<?php

namespace Tests\Platforms;

use App\Enums\PlatformType;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Exceptions\PlatformNotSubscribableException;
use App\Platforms\Platforms;
use App\Platforms\Rss;
use App\Platforms\SoundCloud;
use App\Platforms\Web;
use App\Platforms\YouTube;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformsTest extends TestCase
{
    private function platforms(): Platforms
    {
        return $this->app->make(Platforms::class);
    }

    #[Test]
    public function it_resolves_youtube_urls()
    {
        foreach (array_keys(Data::YOUTUBE_URLS_TO_IDS) as $url) {
            $this->assertEquals(PlatformType::YouTube, $this->platforms()->typeForUrl($url), "Failed to identify as a YouTube URL: $url");
        }
    }

    #[Test]
    public function it_resolves_soundcloud_urls()
    {
        $urls = [
            'https://soundcloud.com/forss/flickermood',
            'https://www.soundcloud.com/forss',
            'https://m.soundcloud.com/forss/sets/soulhack',
            'https://on.soundcloud.com/AbC123xYz',
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::SoundCloud, $this->platforms()->typeForUrl($url), "Failed to identify as a SoundCloud URL: $url");
        }
    }

    #[Test]
    public function it_resolves_web_urls()
    {
        $urls = [
            'https://www.theonion.com/fuck-everything-were-doing-five-blades-1819584036',
            'https://www.engadget.com/2010-06-24-apple-responds-over-iphone-4-reception-issues-youre-holding-th.html',
            'https://www.nytimes.com/2024/04/13/movies/ai-blu-ray-true-lies.html',
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::Web, $this->platforms()->typeForUrl($url), "Failed to identify as a Web URL: $url");
        }
    }

    #[Test]
    public function it_resolves_the_concrete_platform_for_a_type()
    {
        $this->assertInstanceOf(YouTube::class, $this->platforms()->for(PlatformType::YouTube));
        $this->assertInstanceOf(Web::class, $this->platforms()->for(PlatformType::Web));
        $this->assertInstanceOf(Rss::class, $this->platforms()->for(PlatformType::Rss));
        $this->assertInstanceOf(SoundCloud::class, $this->platforms()->for(PlatformType::SoundCloud));
    }

    #[Test]
    public function it_resolves_subscription_urls_to_their_platform_or_rss()
    {
        // An unrecognized clip URL is a web article. An unrecognized
        // subscription URL is a feed.
        $this->assertEquals(
            PlatformType::YouTube,
            $this->platforms()->subscribableTypeForUrl('https://www.youtube.com/@channel'),
        );
        $this->assertEquals(
            PlatformType::SoundCloud,
            $this->platforms()->subscribableTypeForUrl('https://soundcloud.com/forss'),
        );
        $this->assertEquals(
            PlatformType::Rss,
            $this->platforms()->subscribableTypeForUrl('https://riversidegazette.com/feed.xml'),
        );
    }

    #[Test]
    public function it_resolves_the_concrete_platform_straight_from_a_url()
    {
        $this->assertInstanceOf(YouTube::class, $this->platforms()->forUrl('https://youtube.com/watch?v=abc'));
        $this->assertInstanceOf(Web::class, $this->platforms()->forUrl('https://www.nytimes.com/2024/04/13/movies/ai.html'));
    }

    #[Test]
    public function it_returns_a_subscribable_platform_for_a_subscribable_type()
    {
        $this->assertInstanceOf(SubscribablePlatform::class, $this->platforms()->subscribableFor(PlatformType::YouTube));
        $this->assertInstanceOf(SubscribablePlatform::class, $this->platforms()->subscribableFor(PlatformType::Rss));
        $this->assertInstanceOf(SubscribablePlatform::class, $this->platforms()->subscribableFor(PlatformType::SoundCloud));
    }

    #[Test]
    public function it_rejects_a_non_subscribable_type()
    {
        $this->expectException(PlatformNotSubscribableException::class);

        $this->platforms()->subscribableFor(PlatformType::Web);
    }
}
