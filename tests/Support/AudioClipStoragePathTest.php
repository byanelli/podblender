<?php

namespace Tests\Support;

use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Support\AudioClipStoragePath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AudioClipStoragePathTest extends TestCase
{
    #[Test]
    public function it_combines_author_and_title_into_a_slug_with_mp3_extension()
    {
        $source = AudioSource::factory()->create([
            'name'          => 'The Baker\'s Dozen Podcast',
            'platform_type' => PlatformType::Web,
        ]);
        $clip = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'title'           => 'COOKING | Part 1 | Making Grandma\'s "Sourdough"',
        ]);

        $path = AudioClipStoragePath::for($clip->audioSource->name, $clip->title);

        $this->assertStringStartsWith(
            'the-bakers-dozen-podcast-cooking-part-1-making-grandmas-sourdough',
            $path
        );
        $this->assertMatchesRegularExpression('/-[a-z0-9]{6}\.mp3$/', $path);
    }

    #[Test]
    public function it_falls_back_when_the_slug_has_no_ascii_characters()
    {
        $path = AudioClipStoragePath::for('作者', '标题');

        $this->assertMatchesRegularExpression('/^clip-[a-z0-9]{6}\.mp3$/', $path);
    }

    #[Test]
    public function it_names_a_thumbnail_after_the_audio_it_belongs_to()
    {
        $this->assertSame(
            'the-bakers-dozen-cooking-abc123.jpg',
            AudioClipStoragePath::thumbnailFor('the-bakers-dozen-cooking-abc123.mp3')
        );
    }

    #[Test]
    public function it_appends_the_thumbnail_extension_to_a_path_that_has_none()
    {
        // Clips created before storage paths were slugs are bare UUIDs, so
        // there's no extension to swap out — only one to add.
        $this->assertSame(
            'f556d3ed-fd1e-486c-aec8-8dfff0657cf6.jpg',
            AudioClipStoragePath::thumbnailFor('f556d3ed-fd1e-486c-aec8-8dfff0657cf6')
        );
    }

    #[Test]
    public function it_never_returns_a_path_already_in_use()
    {
        $source = AudioSource::factory()->create(['platform_type' => PlatformType::Web]);
        $existing = AudioClip::factory()->create([
            'audio_source_id' => $source->id,
            'title'           => 'Same title',
            'storage_path'    => 'some-slug-abc123.mp3',
        ]);

        $path = AudioClipStoragePath::for($source->name, 'Same title');

        $this->assertNotSame($existing->storage_path, $path);
        $this->assertFalse(
            AudioClip::query()->where('storage_path', $path)->exists()
        );
    }
}
