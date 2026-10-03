<?php

namespace Tests\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Client;
use App\Platforms\Contracts\Chapter;
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

    private function isCropDetection(PendingProcess $process): bool
    {
        return collect((array) $process->command)->contains(fn (string $argument) => Str::contains($argument, 'cropdetect'));
    }

    /**
     * Fake an encode that writes $contents to the output file, a duration
     * probe that reports $duration, and a crop detection that prints
     * $cropDetectOutput.
     */
    private function fakeEncodeProducing(
        string $contents,
        string $duration = '00:00:05.06',
        string $cropDetectOutput = '',
    ): \Closure {
        return function (PendingProcess $process) use ($contents, $duration, $cropDetectOutput) {
            if ($this->isDurationProbe($process)) {
                return Process::result(errorOutput: "  Duration: $duration, start: 0.000000, bitrate: 128 kb/s");
            }

            if ($this->isCropDetection($process)) {
                return Process::result(errorOutput: $cropDetectOutput);
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
    public function it_writes_chapters_ending_where_the_next_starts_and_drops_those_past_the_end()
    {
        $metadata = null;

        Process::fake(['*' => function (PendingProcess $process) use (&$metadata) {
            $command = collect((array) $process->command)->map(fn (string $a) => Str::replace("'", '', $a));

            if (! $this->isDurationProbe($process)) {
                // The second input is the chapter metadata file.
                $metadata = file_get_contents($command[$command->search('-i') + 3]);
                $this->assertContains('-map_chapters', $command);
            }

            return $this->fakeEncodeProducing('chaptered audio')($process);
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->addChapters('in.mp3', [
            new Chapter(0, 'Intro'),
            new Chapter(60, 'A=B; #1 \\ done'),
            new Chapter(200, 'Past the end'),
        ], 120);

        $this->assertEquals(
            ";FFMETADATA1\n"
            ."[CHAPTER]\nTIMEBASE=1/1\nSTART=0\nEND=60\ntitle=Intro\n"
            ."[CHAPTER]\nTIMEBASE=1/1\nSTART=60\nEND=120\ntitle=A\\=B\\; \\#1 \\\\ done\n",
            $metadata,
        );
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

    /**
     * cropdetect's stderr for a 480x360 image, reporting $crop.
     */
    private function cropDetectOutput(string $crop): string
    {
        return <<<HEREDOC
  Stream #0:0: Video: mjpeg (Baseline), yuvj420p(pc, bt470bg/unknown/unknown), 480x360 [SAR 1:1 DAR 4:3], 25 tbr, 25 tbn
[Parsed_cropdetect_0 @ 0x600000] x1:0 x2:479 y1:46 y2:313 w:480 h:268 x:0 y:46 pts:0 t:0.000000 limit:0.094118 crop=$crop
HEREDOC;
    }

    #[Test]
    public function it_crops_off_black_bars_before_squaring_an_image()
    {
        $png = sys_get_temp_dir().'/'.Uuid::uuid4().'.png';

        // YouTube's hqdefault thumbnail letterboxes a 16:9 frame in a 4:3 image.
        Process::fake(['*' => $this->fakeEncodeProducing(
            'jpeg bytes',
            cropDetectOutput: $this->cropDetectOutput('480:268:0:46'),
        )]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->imageToSquareJpeg($png, 1400);

        Process::assertRan(fn (PendingProcess $process) => collect($process->command)
            ->map(fn (string $argument) => Str::replace("'", '', $argument))
            ->contains('crop=480:268:0:46,crop=min(iw,ih):min(iw,ih),scale=1400:1400:flags=lanczos'));
    }

    #[Test]
    public function it_ignores_a_detected_area_too_small_to_be_bars()
    {
        $png = sys_get_temp_dir().'/'.Uuid::uuid4().'.png';

        // A mostly dark image, where cropdetect finds only a small bright area.
        Process::fake(['*' => $this->fakeEncodeProducing(
            'jpeg bytes',
            cropDetectOutput: $this->cropDetectOutput('100:268:190:46'),
        )]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->imageToSquareJpeg($png, 1400);

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
