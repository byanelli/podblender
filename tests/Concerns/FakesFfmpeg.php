<?php

namespace Tests\Concerns;

use App\Apis\Ffmpeg\Contracts\Client;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesFfmpeg
{
    protected function fakeFfmpeg(int $duration = 1): void
    {
        $this->app->bind(Client::class, fn () => new readonly class($duration) implements Client
        {
            public function __construct(private int $duration) {}

            public function getDuration(string $path): int
            {
                return $this->duration;
            }

            public function combineMp3s(array $mp3s): string
            {
                return collect($mp3s)->map(fn ($mp3) => file_get_contents($mp3))->implode('');
            }

            public function pcmToMp3(string $pcm, int $sampleRate): string
            {
                // Pretend transcode: copy the bytes to a fresh .mp3 path (the
                // real method returns a distinct file), so a convertTextToSpeech
                // round-trip reassembles the original text.
                $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

                copy($pcm, $outputPath);

                return $outputPath;
            }
        });
    }
}
