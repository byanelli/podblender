<?php

namespace Tests\Jobs;

use App\Jobs\DownloadAndStoreYoutubeVideo;
use App\Models\AudioClip;
use App\Models\AudioSource;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesFfmpeg;
use Tests\Concerns\FakesStorage;
use Tests\Concerns\FakesYoutubeDownloader;
use Tests\TestCase;

class DownloadAndStoreYoutubeVideoTest extends TestCase
{
    use FakesYoutubeDownloader, FakesStorage, FakesDispatcher, FakesFfmpeg;

    #[Test]
    public function it_downloads_and_stores_youtube_videos(): void
    {
        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
            AudioClip::COL_PROCESSING => true,
        ]);

        $downloadPath = sys_get_temp_dir().'/download-test.mp3';
        $downloadContents = 'foo';

        $storage = Storage::fake();

        $this->assertTrue($clip->processing);
        $storage->assertMissing($clip->storage_path);

        $this->fakeYoutubeDownloader($downloadPath, $downloadContents);
        $this->fakeFfmpeg($duration = 100);

        dispatch(new DownloadAndStoreYoutubeVideo($clip));

        $clip->refresh();

        $this->assertFalse($clip->processing);
        $storage->assertExists($clip->storage_path);
        $this->assertEquals($duration, $clip->duration);
        $this->assertEquals($downloadContents, $storage->get($clip->storage_path));
        $this->assertFileDoesNotExist($downloadPath);
    }

    #[Test]
    public function it_deletes_clip_and_temp_files_on_error(): void
    {
        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
            AudioClip::COL_PROCESSING => true,
        ]);

        $downloadPath = sys_get_temp_dir().'/download-test.mp3';
        $downloadContents = 'foo';

        $this->assertModelExists($clip);

        $this->fakeYoutubeDownloader($downloadPath, $downloadContents);
        $this->fakeStorageThatThrowsExceptionOnPut();

        // Laravel's fake dispatcher doesn't work here, presumably because we throw an exception during the job.
        $this->fakeExceptionSuppressingDispatcher();

        dispatch(new DownloadAndStoreYoutubeVideo($clip));

        $this->assertModelMissing($clip);
        $this->assertFileDoesNotExist($downloadPath);
    }
}
