<?php

namespace Tests\Concerns;

use App\Apis\Ffmpeg\Contracts\Client;
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
        });
    }
}
