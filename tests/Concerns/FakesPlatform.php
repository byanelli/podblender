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
        string    $id = '',
        string    $url = '',
        ?Metadata $metadata = null,
        string    $audioPath = '',
        string    $audioContent = '',
    ): void {
        $platform = new readonly class (
            $id,
            $url,
            $metadata,
            $audioPath,
            $audioContent,
        ) implements Platform {
            public function __construct(
                private string    $id = '',
                private string    $url = '',
                private ?Metadata $metadata = null,
                private string    $audioPath = '',
                private string    $audioContent = '',
            ) {}

            public function getIdFromUrl(string $url): string {
                return $this->id;
            }

            public function getUrlFromId(string $id): string {
                return $this->url;
            }

            public function getMetadata(string $id): Metadata {
                return $this->metadata;
            }

            public function downloadAudio(string $id): string {
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
