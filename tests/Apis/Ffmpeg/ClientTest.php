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
    protected function setUp(): void {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    #[Test]
    public function it_combines_mp3s() {
        $mp3s = [
            sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
            sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3',
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
}
