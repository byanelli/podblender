<?php

namespace Tests\Concerns;

use App\Covers\Contracts\CoverGenerator;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesCoverGenerator
{
    /**
     * Replaces the real generator, which takes nearly 0.1s per image. Tests of
     * the image itself use GdCoverGenerator directly.
     */
    protected function fakeCoverGenerator(): void
    {
        $this->app->bind(CoverGenerator::class, fn () => new readonly class implements CoverGenerator
        {
            public function generate(string $title, int $variant): string
            {
                // Like the real generator, returns a file the caller must
                // store and then delete.
                $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

                file_put_contents($path, "a pretend cover for \"{$title}\", variant {$variant}");

                return $path;
            }
        });
    }

    /**
     * A generator that always throws, for checking that a feed is still
     * created when its cover fails.
     */
    protected function fakeCoverGeneratorThatFails(string $message = 'no font'): void
    {
        $this->app->bind(CoverGenerator::class, fn () => new readonly class($message) implements CoverGenerator
        {
            public function __construct(private string $message) {}

            public function generate(string $title, int $variant): string
            {
                throw new \RuntimeException($this->message);
            }
        });
    }
}
