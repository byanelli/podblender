<?php

namespace Tests\Apis\Ffmpeg;

use App\Apis\Ffmpeg\Client;
use App\Platforms\Contracts\Chapter;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * Runs the vendored ffmpeg binary, to check that it writes the chapters as ID3 frames. Skipped where the binary isn't
 * installed.
 */
class ChaptersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(base_path('vendor/bin/ffmpeg'))) {
            $this->markTestSkipped('The vendored ffmpeg binary is not installed.');
        }

        Process::preventStrayProcesses(false);
    }

    #[Test]
    public function it_writes_id3_chapter_frames()
    {
        $input = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

        Process::path(base_path('vendor/bin'))
            ->run(['./ffmpeg', '-y', '-f', 'lavfi', '-i', 'sine=d=30', '-b:a', '64k', $input])
            ->throw();

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $output = $client->addChapters($input, [
            new Chapter(0, 'Intro'),
            new Chapter(10, 'A=B; #1'),
        ], 30);

        $probe = Process::path(base_path('vendor/bin'))->run(['./ffmpeg', '-i', $output])->errorOutput();
        $header = (string) file_get_contents($output, length: 4096);

        $this->assertStringContainsString('start 10.000000, end 30.000000', $probe);
        $this->assertMatchesRegularExpression('/title\s+: A=B; #1/', $probe);
        $this->assertSame(2, substr_count($header, 'CHAP'));
        $this->assertSame(1, substr_count($header, 'CTOC'));
        $this->assertSame(30, $client->getDuration($output));

        unlink($input);
        unlink($output);
    }
}
