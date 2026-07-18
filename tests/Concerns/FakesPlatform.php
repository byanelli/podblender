<?php

namespace Tests\Concerns;

use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Web;
use App\Platforms\YouTube;
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
        $platform = new readonly class($clipMetadata, $sourceMetadata, $clipMetadataList, $audioPath, $audioContent) implements SubscribablePlatform
        {
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

            public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
            {
                return $this->clipMetadataList;
            }
        };

        // Bind the fake against every concrete platform so the Platforms service resolves it from the container no
        // matter which type a URL maps to. It's a SubscribablePlatform so subscribableFor() accepts it too.
        $this->app->instance(YouTube::class, $platform);
        $this->app->instance(Web::class, $platform);
    }
}
