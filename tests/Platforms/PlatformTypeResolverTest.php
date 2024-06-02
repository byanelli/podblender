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
            // todo: more
        ];

        foreach ($urls as $url) {
            $this->assertEquals(PlatformType::Web, $resolver->fromUrl($url), "Failed to identify as a YouTube URL: $url");
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
            $this->assertEquals(PlatformType::SoundCloud, $resolver->fromUrl($url), "Failed to identify as a YouTube URL: $url");
        }
    }
}
