<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Apis\YtDlp;

use App\Apis\YtDlp\Client;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    #[Test]
    public function it_gets_metadata()
    {
        $url = 'https://youtube.com/watch?v=wp4i5g490wg7u';

        Process::fake(["'./yt-dlp' '--dump-json' '$url'" => Process::result(json_encode($fakeMetadata = [
            'id' => 1,
            'title' => 'Some video',
            'description' => 'Lorem ipsum',
            'channel_id' => '12039u30qg9oir48jg3',
            'channel' => 'Some channel',
            'duration' => 199,
        ]))]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $metadata = $client->getMetadata($url);

        $this->assertEquals($fakeMetadata, $metadata);
    }

    #[Test]
    public function it_downloads_audio()
    {
        $url = 'https://youtube.com/watch?v=wp4i5g490wg7u';

        $file = '';

        Process::fake(["'./yt-dlp' '-x' '--audio-format=mp3' '--audio-quality=2' '-o' '*' '$url'" => function (PendingProcess $process) use (&$file) {
            $file = collect($process->command)->first(fn ($s) => Str::endsWith($s, '.mp3'));

            touch($file);

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio($url);

        $this->assertFileExists($file);
    }
}
