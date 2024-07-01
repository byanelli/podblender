<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioClip;
use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Metadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class CreateAudioClipTest extends TestCase
{
    use FakesPlatform;

    #[Test]
    public function it_creates_an_audio_clip()
    {
        $url = 'https://youtube.com/watch?v='.($id = 'lijwliejfwlef');

        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: $title = 'foo',
                description: $description = 'zzz',
                canonicalUrl: $canonicalUrl = $url,
                source: new SourceMetadata(
                    name: $sourceName = 'bar',
                    canonicalUrl: $souceUrl = 'https://youtube.com/channel/9340e9tjh490e5'
                ),
            ),
        );

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $url)
            ->load(AudioClip::REL_AUDIO_SOURCE);

        $this->assertEquals($url, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals(0, $clip->duration);
        $this->assertEquals($souceUrl, $clip->audioSource->platform_id);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertTrue($clip->processing);
    }
}
