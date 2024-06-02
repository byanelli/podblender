<?php

namespace Tests\Concerns;

use App\Enums\PlatformType;
use App\Platforms\Metadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\PlatformFactory;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesPlatform
{
    protected function fakePlatform(
        ?Metadata $metadata = null,
        string    $audioPath = '',
        string    $audioContent = '',
    ): void {
        $platform = new readonly class (
            $metadata,
            $audioPath,
            $audioContent,
        ) implements Platform {
            public function __construct(
                private ?Metadata $metadata = null,
                private string    $audioPath = '',
                private string    $audioContent = '',
            ) {}

            public function getCanonicalUrl(string $url): string {
                return $url;
            }

            public function getMetadata(string $url): Metadata {
                return $this->metadata;
            }

            public function downloadAudio(string $url): string {
                file_put_contents($this->audioPath, $this->audioContent);

                return $this->audioPath;
            }
        };

        $this->app->bind(PlatformFactory::class, fn() => new readonly class ($platform) implements PlatformFactory {
            public function __construct(private Platform $platform) {}

            public function make(PlatformType $platformType): Platform {
                return $this->platform;
            }
        });
    }
}
