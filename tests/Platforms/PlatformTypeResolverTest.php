<?php

namespace Tests\Platforms;

use App\Enums\PlatformType;
use App\Platforms\PlatformTypeResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformTypeResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_youtube() {
        /** @var PlatformTypeResolver $resolver */
        $resolver = $this->app->make(PlatformTypeResolver::class);

        foreach (array_keys(Data::YOUTUBE_URLS_TO_IDS) as $url) {
            $this->assertEquals(PlatformType::YouTube, $resolver->fromUrl($url), "Failed to identify as a YouTube URL: $url");
        }
    }

    #[Test]
    public function it_resolves_web() {
        /** @var PlatformTypeResolver $resolver */
        $resolver = $this->app->make(PlatformTypeResolver::class);

        $urls = [
            'https://www.theonion.com/fuck-everything-were-doing-five-blades-1819584036',
            'https://www.engadget.com/2010-06-24-apple-responds-over-iphone-4-reception-issues-youre-holding-th.html',
            'https://www.nytimes.com/2024/04/13/movies/ai-blu-ray-true-lies.html',
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::Web, $resolver->fromUrl($url), "Failed to identify as a Web URL: $url");
        }
    }

    #[Test]
    public function it_resolves_soundcloud() {
        /** @var PlatformTypeResolver $resolver */
        $resolver = $this->app->make(PlatformTypeResolver::class);

        $urls = [
            'https://soundcloud.com/kendrick-lamar-music/not-like-us',
            'https://on.soundcloud.com/TAdDMxWcmzCW8TMi8',
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::SoundCloud, $resolver->fromUrl($url), "Failed to identify as a SoundCloud URL: $url");
        }
    }

    #[Test]
    public function it_resolves_twitch() {
        /** @var PlatformTypeResolver $resolver */
        $resolver = $this->app->make(PlatformTypeResolver::class);

        $urls = [
            'https://twitch.tv/video/12345',
            'https://twitch.tv/clip/FooBarBaz-lwjeiwljg90gj3wgjijr',
            'https://twitch.com/video/12345',
            'https://twitch.com/clip/FooBarBaz-lwjeiwljg90gj3wgjijr',
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::Twitch, $resolver->fromUrl($url), "Failed to identify as a Twitch URL: $url");
        }
    }
}
