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

    #[Test]
    public function it_combines_mp3s()
    {
        $mp3s = [
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
            sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3',
        ];

        Process::fake(["'./ffmpeg' '-y' '-i' 'concat:{$mp3s[0]}|{$mp3s[1]}' '-acodec' 'copy' *" => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            file_put_contents($file, 'combined audio');

            return Process::result();
        }]);

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

        // ffmpeg has been seen to exit 0 having written an empty file. Silently
        // accepting that puts a stretch of nothing into the finished episode, so
        // it has to be an error the job can retry.
        Process::fake(["'./ffmpeg' '-y' '-i' 'concat:{$mp3s[0]}|{$mp3s[1]}' '-acodec' 'copy' *" => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            touch($file);

            return Process::result(errorOutput: 'Output file is empty, nothing was encoded');
        }]);

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

        Process::fake(['*' => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            touch($file);

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wrote no audio');

        $client->pcmToMp3($pcm, 24000);
    }

    #[Test]
    public function it_transcodes_pcm_to_mp3()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            file_put_contents($file, 'transcoded audio');

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $mp3 = $client->pcmToMp3($pcm, 24000);

        $this->assertFileExists($mp3);
        $this->assertStringEndsWith('.mp3', $mp3);

        // The raw PCM has no container, so ffmpeg must be told its format.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)->contains('s16le')
            && collect($process->command)->contains('24000')
            && collect($process->command)->contains('128k'));
    }

    #[Test]
    public function it_tells_ffmpeg_to_overwrite_its_output()
    {
        $pcm = sys_get_temp_dir().'/'.Uuid::uuid4().'.pcm';

        Process::fake(['*' => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            file_put_contents($file, 'transcoded audio');

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->pcmToMp3($pcm, 24000);

        // Without -y, ffmpeg prompts before overwriting an existing file, reads
        // EOF from our non-interactive stdin, and exits *successfully* having
        // written nothing — leaving whatever was already at that path. That
        // failure is silent, so the flag is what keeps it from happening.
        Process::assertRan(fn (PendingProcess $process) => collect($process->command)->contains('-y'));
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
