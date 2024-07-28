<?php

namespace Tests\Concerns;

use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\PlatformFactory;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Metadata;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesPlatform
{
    protected function fakePlatform(
        ?ClipMetadata $clipMetadata = null,
        ?SourceMetadata $sourceMetadata = null,
        array $clipMetadataList = [],
        ?string $audioPath = null,
        ?string $audioContent = null,
    ): void {
        $platform = new readonly class (
            $clipMetadata,
            $sourceMetadata,
            $clipMetadataList,
            $audioPath,
            $audioContent,
        ) implements Platform {
            public function __construct(
                private ?ClipMetadata $clipMetadata = null,
                private ?SourceMetadata $sourceMetadata = null,
                private array $clipMetadataList = [],
                private ?string $audioPath = null,
                private ?string $audioContent = null,
            ) {}

            public function getClipMetadata(string $clipUrl): ClipMetadata
            {
                return $this->clipMetadata;
            }

            public function downloadAudio(string $clipUrl): string
            {
                file_put_contents(
                    $path = $this->audioPath ?: sys_get_temp_dir().DIRECTORY_SEPARATOR.Uuid::uuid4()->toString(),
                    $this->audioContent ?: Uuid::uuid4()->toString(),
                );

                return $path;
            }

            public function getSourceMetadata(string $sourceUrl): SourceMetadata
            {
                return $this->sourceMetadata;
            }

            public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
            {

            }

            public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
            {
                return $this->clipMetadataList;
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
