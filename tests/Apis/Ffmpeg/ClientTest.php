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

        Process::fake(["'./ffmpeg' '-i' 'concat:{$mp3s[0]}|{$mp3s[1]}' '-acodec' 'copy' *" => function (PendingProcess $process) {
            $file = Str::replace("'", '', Arr::last($process->command));

            touch($file);

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $file = $client->combineMp3s($mp3s);

        $this->assertFileExists($file);
    }

    #[Test]
    public function it_gets_duration()
    {
        $mp3 = sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3';

        Process::fake(["'./ffmpeg' '-i' '{$mp3}'" => function (PendingProcess $process) {
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
