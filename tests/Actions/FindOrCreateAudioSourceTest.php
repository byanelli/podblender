<?php

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioSource;
use App\Enums\PlatformType;
use App\Models\AudioSource;
use App\Platforms\Contracts\SourceMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FindOrCreateAudioSourceTest extends TestCase
{
    private function action(): FindOrCreateAudioSource
    {
        return $this->app->make(FindOrCreateAudioSource::class);
    }

    #[Test]
    public function it_creates_a_source_when_none_exists()
    {
        $metadata = new SourceMetadata(
            name: $name = 'Some channel',
            canonicalUrl: $url = 'https://youtube.com/@zzz',
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertDatabaseCount('audio_sources', 1);
        $this->assertEquals($url, $source->platform_url);
        $this->assertEquals($name, $source->name);
        $this->assertEquals(PlatformType::YouTube, $source->platform_type);
    }

    #[Test]
    public function it_returns_the_existing_source_matching_platform_type_and_url()
    {
        $existing = AudioSource::factory()->create([
            AudioSource::COL_PLATFORM_TYPE => PlatformType::YouTube,
            AudioSource::COL_PLATFORM_URL => $url = 'https://youtube.com/@zzz',
            AudioSource::COL_NAME => 'Original name',
        ]);

        // Different name in the metadata: firstOrCreate matches on type + url, so no new row and no rename.
        $metadata = new SourceMetadata(
            name: 'A different name',
            canonicalUrl: $url,
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertTrue($source->is($existing));
        $this->assertDatabaseCount('audio_sources', 1);
        $this->assertEquals('Original name', $source->name);
    }
}
