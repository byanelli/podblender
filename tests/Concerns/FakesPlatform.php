<?php

namespace Tests\Concerns;

use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Metadata;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesPlatform
{
    protected function fakePlatform(
        ?ClipMetadata $clipMetadata = null,
        string $audioPath = '',
        string $audioContent = '',
    ): void {
        $platform = new readonly class($clipMetadata, $audioPath, $audioContent) implements Platform
        {
            public function __construct(
                private ?ClipMetadata $clipMetadata = null,
                private string $audioPath = '',
                private string $audioContent = '',
            ) {}

            public function getCanonicalUrl(string $url): string
            {
                return $url;
            }

            public function getMetadata(string $url): Metadata
            {
                return $this->metadata;
            }

            public function getClipMetadata(string $clipUrl): ClipMetadata
            {
                return $this->clipMetadata;
            }

            public function downloadAudio(string $clipUrl): string
            {
                file_put_contents($this->audioPath, $this->audioContent);

                return $this->audioPath;
            }

            public function getSourceMetadata(string $sourceUrl): SourceMetadata
            {
                // TODO: Implement getSourceMetadata() method.
            }

            public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
            {
                // TODO: Implement getClipUrlsPublishedSince() method.
            }
        };

        $this->app->bind(PlatformFactory::class, fn () => new readonly class($platform) implements PlatformFactory
        {
            public function __construct(private Platform $platform) {}

            public function make(PlatformType $platformType): Platform
            {
                return $this->platform;
            }
        });
    }
}
