<?php

namespace Tests\Concerns;

use App\Apis\Ffmpeg\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesFfmpeg
{
    protected function fakeFfmpeg(int $duration=1): void {
        // todo implement interface
        $this->app->bind(Client::class, fn () => new readonly class ($this->app, $this->app->make(Factory::class), $duration) extends Client {
            public function __construct(Application $app, Factory $processFactory, private int $duration) {
                parent::__construct($app, $processFactory);
            }

            public function getDuration(string $path): int {
                return $this->duration;
            }
        });
    }
}
