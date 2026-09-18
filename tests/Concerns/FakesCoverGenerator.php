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
     * Stand in for the real generator, which spends most of a tenth of a second
     * drawing an image no test looks at. Tests that care what a cover looks
     * like exercise GdCoverGenerator directly.
     */
    protected function fakeCoverGenerator(): void
    {
        $this->app->bind(CoverGenerator::class, fn () => new readonly class implements CoverGenerator
        {
            public function generate(string $title, int $variant): string
            {
                // The real generator hands back a file the caller has to store
                // and then delete, so this one does too.
                $path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.jpg';

                file_put_contents($path, "a pretend cover for \"{$title}\", variant {$variant}");

                return $path;
            }
        });
    }

    /**
     * A generator that can't draw anything, for checking that a feed is still
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
