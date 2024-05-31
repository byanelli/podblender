<?php

namespace Tests\Jobs;

use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesFfmpeg;
use Tests\Concerns\FakesPlatform;
use Tests\Concerns\FakesStorage;
use Tests\TestCase;

class DownloadAndStoreAudioClipTest extends TestCase
{
    use FakesPlatform, FakesStorage, FakesDispatcher, FakesFfmpeg;

    #[Test]
    public function it_downloads_and_stores_audio_clips(): void
    {
        $this->fakePlatform(
            id: $id = '123',
            audioPath: $downloadPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
            audioContent: $downloadContents = 'foo',
        );

        $this->fakeFfmpeg($duration = 100);

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_PLATFORM_ID => $id,
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
            AudioClip::COL_PROCESSING => true,
        ]);

        $storage = Storage::fake();

        $this->assertTrue($clip->processing);
        $storage->assertMissing($clip->storage_path);

        dispatch(new DownloadAndStoreAudioClip($clip));

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
        $this->fakePlatform(
            id: $id = '123',
            audioPath: $downloadPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
        );

        $this->fakeStorageThatThrowsExceptionOnPut();

        // Laravel's fake dispatcher doesn't work here, presumably because we throw an exception during the job.
        $this->fakeExceptionSuppressingDispatcher();

        /** @var AudioClip $clip */
        $clip = AudioClip::factory()->create([
            AudioClip::COL_PLATFORM_ID => $id,
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
            AudioClip::COL_PROCESSING => true,
        ]);

        $this->assertModelExists($clip);

        dispatch(new DownloadAndStoreAudioClip($clip));

        $this->assertModelMissing($clip);
        $this->assertFileDoesNotExist($downloadPath);
    }
}
