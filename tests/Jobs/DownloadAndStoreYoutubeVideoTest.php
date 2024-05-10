<?php

namespace Tests\Jobs;

use App\Jobs\DownloadAndStoreYoutubeVideo;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\YoutubeDownloader\Client;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DownloadAndStoreYoutubeVideoTest extends TestCase
{
    #[Test]
    public function it_downloads_and_stores_youtube_videos(): void
    {
        /** @var AudioSource $source */
        $source = AudioSource::factory()->create();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => $source->id,
            AudioClip::COL_PROCESSING => true,
        ]);

        $this->assertTrue($clip->processing);

        Storage::fake();

        $this->app->bind(Client::class, function () { return new class ($this->app, Process::fake()) extends Client {
            public function downloadAudio(string $url): string {
                $outputPath = sys_get_temp_dir()."/downlad-test.mp3";

                file_put_contents($outputPath, 'foo');

                return $outputPath;
            }
        };});

        dispatch(new DownloadAndStoreYoutubeVideo($clip));

        $clip->refresh();

        $this->assertFalse($clip->processing);
        Storage::assertExists($clip->storage_path);
        $this->assertEquals('foo', Storage::get($clip->storage_path));
        $this->assertFalse(file_exists(sys_get_temp_dir()."/downlad-test.mp3"));
    }
}
