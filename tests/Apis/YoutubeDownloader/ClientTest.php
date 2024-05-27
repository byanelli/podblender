<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Apis\YoutubeDownloader;

use App\Apis\YtDlp\Client;
use App\Apis\YtDlp\DownloadException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    #[Test]
    public function it_gets_metadata() {
        $url = 'https://youtube.com/watch?v=wp4i5g490wg7u';

        Process::fake(["./yt-dlp --dump-json $url" => Process::result(json_encode($fakeMetadata = [
            'id' => 1,
            'title' => 'Some video',
            'description' => 'Lorem ipsum',
            'channel_id' => '12039u30qg9oir48jg3',
            'channel' => 'Some channel',
            'duration' => 199,
        ]))]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $metadata = $client->getRawMetadata($url);

        $this->assertEquals($fakeMetadata, $metadata);
    }

    #[Test]
    public function it_downloads_audio() {
        $url = 'https://youtube.com/watch?v=wp4i5g490wg7u';

        $file = '';

        Process::fake(["./yt-dlp -x --audio-format=mp3 --audio-quality=2 -o * $url" => function (PendingProcess $process) use (&$file) {
            $file = collect(explode(' ', $process->command))->first(fn($s) => Str::endsWith($s, '.mp3'));

            touch($file);

            return Process::result();
        }]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio($url);

        $this->assertFileExists($file);
    }

    #[Test]
    public function it_rejects_invalid_input_when_getting_metadata() {
        $this->expectException(\InvalidArgumentException::class);

        Process::fake();

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->getRawMetadata('https://youtube.com/watch?v=zzz; rm -rf /some/important/path #');
    }

    #[Test]
    public function it_rejects_invalid_input_when_downloading_audio() {
        $this->expectException(\InvalidArgumentException::class);

        Process::fake();

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $client->downloadAudio('https://youtube.com/watch?v=zzz; rm -rf /some/important/path #');
    }
}
