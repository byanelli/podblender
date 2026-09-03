<?php

namespace Tests\Models;

use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Models\AudioSource;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AudioClipTest extends TestCase
{
    #[Test]
    public function it_formats_a_time_of_at_least_an_hour_with_hours()
    {
        $clip = AudioClip::factory()->make(['duration' => 3661]);

        $this->assertEquals('1:01:01', $clip->formatted_time);
    }

    #[Test]
    public function it_formats_a_time_under_an_hour_without_hours()
    {
        $clip = AudioClip::factory()->make(['duration' => 125]);

        $this->assertEquals('2:05', $clip->formatted_time);
    }

    #[Test]
    public function its_platform_type_comes_from_its_audio_source()
    {
        $source = AudioSource::factory()->create(['platform_type' => PlatformType::Web]);
        $clip = AudioClip::factory()->create(['audio_source_id' => $source->id]);

        $this->assertEquals(PlatformType::Web, $clip->platform_type);
    }

    #[Test]
    public function its_audio_url_is_the_public_url_for_its_storage_path()
    {
        $clip = $this->clip();

        $this->assertStringContainsString('/storage/some/path.mp3', $clip->audio_url);
        $this->assertStringStartsWith('http', $clip->audio_url);
    }

    #[Test]
    public function its_audio_url_is_populated_when_preview_is_disabled()
    {
        // The RSS enclosure needs a URL whether or not the browser can play
        // the file back, so audio_url ignores the preview setting entirely.
        Config::set('audio-preview.enabled', false);

        $clip = $this->clip();

        $this->assertStringContainsString('/storage/some/path.mp3', $clip->audio_url);
    }

    #[Test]
    public function its_audio_url_is_populated_when_the_default_disk_is_not_local()
    {
        $this->useS3Disk();

        $clip = $this->clip();

        $this->assertStringStartsWith('https://files.example.test', $clip->audio_url);
        $this->assertStringContainsString('some/path.mp3', $clip->audio_url);
    }

    #[Test]
    public function its_preview_url_is_its_audio_url_when_preview_is_available()
    {
        Config::set('audio-preview.enabled', true);

        $clip = $this->clip();

        $this->assertSame($clip->audio_url, $clip->preview_url);
    }

    #[Test]
    public function its_preview_url_is_null_when_preview_is_disabled()
    {
        Config::set('audio-preview.enabled', false);

        $clip = $this->clip();

        $this->assertNull($clip->preview_url);
    }

    #[Test]
    public function its_preview_url_is_null_when_the_default_disk_is_not_local()
    {
        Config::set('audio-preview.enabled', true);
        $this->useS3Disk();

        $clip = $this->clip();

        $this->assertNull($clip->preview_url);
    }

    /**
     * Point the default disk at a public S3-compatible bucket, as production
     * does: a configured "url" means Storage::url() returns that public URL.
     */
    private function useS3Disk(): void
    {
        Config::set('filesystems.default', 's3');
        Config::set('filesystems.disks.s3', [
            'driver' => 's3',
            'key'    => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'test-bucket',
            'url'    => 'https://files.example.test',
        ]);
    }

    private function clip(): AudioClip
    {
        return AudioClip::factory()->create([
            'storage_path'    => 'some/path.mp3',
            'audio_source_id' => AudioSource::factory()->create()->id,
        ]);
    }
}
