<?php

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioSource;
use App\Enums\AudioSourceType;
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
            authorName: $name,
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertDatabaseCount('audio_sources', 1);
        $this->assertEquals($url, $source->platform_url);
        $this->assertEquals($name, $source->name);
        $this->assertEquals(PlatformType::YouTube, $source->platform_type);
    }

    #[Test]
    public function it_records_what_kind_of_source_it_is_and_who_publishes_it()
    {
        $metadata = new SourceMetadata(
            name: 'Select Lectures',
            canonicalUrl: 'https://youtube.com/playlist?list=PLabc',
            type: AudioSourceType::Playlist,
            authorName: 'Lecture Channel',
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertEquals(AudioSourceType::Playlist, $source->type);

        // A playlist's author is the channel that owns it.
        $this->assertEquals('Lecture Channel', $source->author_name);
    }

    #[Test]
    public function it_defaults_a_source_to_a_channel_that_authors_itself()
    {
        $metadata = new SourceMetadata(
            name: 'Some channel',
            canonicalUrl: 'https://youtube.com/@zzz',
            authorName: 'Some channel',
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertEquals(AudioSourceType::Channel, $source->type);
        $this->assertEquals('Some channel', $source->author_name);
    }

    #[Test]
    public function it_returns_the_existing_source_matching_platform_type_and_url()
    {
        $existing = AudioSource::factory()->create([
            'platform_type' => PlatformType::YouTube,
            'platform_url'  => $url = 'https://youtube.com/@zzz',
            'name'          => 'Original name',
        ]);

        // The name differs, but firstOrCreate matches on type and URL only.
        $metadata = new SourceMetadata(
            name: 'A different name',
            canonicalUrl: $url,
            authorName: 'A different name',
        );

        $source = ($this->action())(PlatformType::YouTube, $metadata);

        $this->assertTrue($source->is($existing));
        $this->assertDatabaseCount('audio_sources', 1);
        $this->assertEquals('Original name', $source->name);
    }
}
