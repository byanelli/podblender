<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioClip;
use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\TestCase;

class FindOrCreateAudioClipTest extends TestCase
{
    use FakesDispatcher;

    #[Test]
    public function it_creates_an_audio_clip()
    {
        $metadata = new ClipMetadata(
            title: $title = 'foo',
            description: $description = 'zzz',
            canonicalUrl: $clipUrl = 'https://youtube.com/watch?v=lijwliejfwlef',
            publishedAt: $publishedAt = now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: $sourceName = 'bar',
                canonicalUrl: $sourceUrl = 'https://youtube.com/channel/9340e9tjh490e5'
            ),
        );

        // We don't want to dispatch the DownloadAndStoreAudioClip job.
        $this->fakeNoOpDispatcher();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata)
            ->load(AudioClip::REL_AUDIO_SOURCE);

        $this->assertEquals($clipUrl, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals($publishedAt, $clip->published_at);
        $this->assertEquals(0, $clip->duration);
        $this->assertEquals($sourceUrl, $clip->audioSource->platform_url);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertTrue($clip->processing);
    }
}
