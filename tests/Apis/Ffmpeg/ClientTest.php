<?php

namespace Tests\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Client;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    /**
     * Whether the command is a duration probe. An encode's command ends with an
     * output path; a probe's ends with the input path, the argument after `-i`.
     */
    private function isDurationProbe(PendingProcess $process): bool
    {
        $command = collect((array) $process->command)
            ->map(fn (string $argument) => Str::replace("'", '', $argument));

        $input = $command->search('-i');

        return $input !== false && $command->count() === $input + 2;
    }

    /**
     * Fake an encode that writes $contents to the output file, and a duration
     * probe that reports $duration.
     */
    private function fakeEncodeProducing(string $contents, string $duration = '00:00:05.06'): \Closure
    {
        return function (PendingProcess $process) use ($contents, $duration) {
            if ($this->isDurationProbe($process)) {
                return Process::result(errorOutput: "  Duration: $duration, start: 0.000000, bitrate: 128 kb/s");
            }

            $file = Str::replace("'", '', Arr::last($process->command));

            $contents === ''
                ? touch($file)
                : file_put_contents($file, $contents);

            return Process::result();
        };
    }

    #[Test]
    public function it_combines_mp3s()
    {
        $mp3s = [
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
        ];

        Process::fake(['*' => $this->fakeEncodeProducing('combined audio')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $file = $client->combineMp3s($mp3s);

        $this->assertFileExists($file);
    }

    #[Test]
    public function it_rejects_a_successful_run_that_wrote_no_audio()
    {
        $mp3s = [
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
        ];

        // ffmpeg has been observed to exit 0 after writing an empty file. That
        // must throw so the job can retry.
        Process::fake(['*' => $this->fakeEncodeProducing('')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wrote no audio');

        $client->combineMp3s($mp3s);
    }

    #[Test]
    public function it_rejects_a_transcode_that_wrote_no_audio()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => $this->fakeEncodeProducing('')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wrote no audio');

        $client->pcmToMp3($pcm, 24000);
    }

    #[Test]
    public function it_rejects_a_transcode_that_wrote_a_file_containing_no_audio()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        // Encoding no samples produces a valid MP3 with headers and no frames.
        // The file is not empty, so only its zero duration identifies it.
        Process::fake(['*' => $this->fakeEncodeProducing('ID3 header but no frames', '00:00:00.00')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wrote no audio');

        $client->pcmToMp3($pcm, 24000);
    }

    #[Test]
    public function it_accepts_a_transcode_shorter_than_a_second()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        // Truncating this duration to whole seconds would give zero and fail
        // the encode.
        Process::fake(['*' => $this->fakeEncodeProducing('half a second of audio', '00:00:00.52')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertFileExists($client->pcmToMp3($pcm, 24000));
    }

    #[Test]
    public function it_encodes_segments_without_headers_that_would_corrupt_a_concatenation()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => $this->fakeEncodeProducing('audio')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->pcmToMp3($pcm, 24000);

        // combineMp3s() concatenates these files byte-wise, so a Xing/LAME
        // header or ID3 tag would end up mid-stream and decode as a broken frame.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)->contains('-write_xing')
            && collect($process->command)->contains('-id3v2_version'));
    }

    #[Test]
    public function it_transcodes_pcm_to_mp3()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => $this->fakeEncodeProducing('transcoded audio')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $mp3 = $client->pcmToMp3($pcm, 24000);

        $this->assertFileExists($mp3);
        $this->assertStringEndsWith('.mp3', $mp3);

        // Raw PCM has no container, so the command must state its format.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)->contains('s16le')
            && collect($process->command)->contains('24000')
            && collect($process->command)->contains('128k'));
    }

    #[Test]
    public function it_tells_ffmpeg_to_overwrite_its_output()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => $this->fakeEncodeProducing('transcoded audio')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->pcmToMp3($pcm, 24000);

        // Without -y, ffmpeg prompts before overwriting an existing file, reads
        // EOF from the non-interactive stdin, and exits 0 having written
        // nothing.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)->contains('-y'));
    }

    #[Test]
    public function it_crops_an_image_to_a_centred_square_jpeg()
    {
        $png = sys_get_temp_dir().'/'.Uuid::uuid4().'.png';

        Process::fake(['*' => $this->fakeEncodeProducing('jpeg bytes')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $jpeg = $client->imageToSquareJpeg($png, 1400);

        $this->assertFileExists($jpeg);
        $this->assertStringEndsWith('.jpg', $jpeg);

        // The crop takes the shorter side, and the scale sets both sides to
        // 1400, the minimum Apple Podcasts accepts.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)
            ->map(fn (string $argument) => Str::replace("'", '', $argument))
            ->contains('crop=min(iw,ih):min(iw,ih),scale=1400:1400:flags=lanczos'));
    }

    #[Test]
    public function it_rejects_a_crop_that_wrote_no_file()
    {
        $png = sys_get_temp_dir().'/'.Uuid::uuid4().'.png';

        // Exit 0 with an empty file, as in the audio tests above.
        Process::fake(['*' => $this->fakeEncodeProducing('')]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wrote no file');

        $client->imageToSquareJpeg($png);
    }

    #[Test]
    public function it_gets_duration()
    {
        $mp3 = sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3';

        Process::fake(["'./ffmpeg' '-y' '-i' '{$mp3}'" => function (PendingProcess $process) {
            return Process::result(errorOutput: <<<'HEREDOC'
Input #0, mp3, from '/path/to/storage/f556d3ed-fd1e-486c-aec8-8dfff0657cf6':
  Metadata:
    encoder         : Lavf58.29.100
  Duration: 00:26:25.54, start: 0.023021, bitrate: 109 kb/s
    Stream #0:0: Audio: mp3, 48000 Hz, stereo, fltp, 109 kb/s
    Metadata:
      encoder         : Lavc58.54
HEREDOC
            );
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertEquals(1585, $client->getDuration($mp3));
    }
}
