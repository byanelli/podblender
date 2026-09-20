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
    /**
     * The publication time last passed to the platform: the fetch cursor. A
     * cursor too far back re-fetches history on every sweep, and one too recent
     * skips clips without an error, so tests assert on it directly.
     */
    private ?\DateTimeInterface $platformPublicationTimeRequested = null;

    protected function platformPublicationTimeRequested(): ?\DateTimeInterface
    {
        return $this->platformPublicationTimeRequested;
    }

    protected function fakePlatform(
        ?ClipMetadata $clipMetadata = null,
        ?SourceMetadata $sourceMetadata = null,
        array $clipMetadataList = [],
        ?string $audioPath = null,
        ?string $audioContent = null,
        ?\Throwable $downloadError = null,
    ): void {
        $recordPublicationTime = function (\DateTimeInterface $time) {
            $this->platformPublicationTimeRequested = $time;
        };

        $platform = new class($clipMetadata, $sourceMetadata, $clipMetadataList, $audioPath, $audioContent, $downloadError, $recordPublicationTime) implements SubscribablePlatform
        {
            public function __construct(
                private readonly ?ClipMetadata $clipMetadata = null,
                private readonly ?SourceMetadata $sourceMetadata = null,
                private readonly array $clipMetadataList = [],
                private readonly ?string $audioPath = null,
                private readonly ?string $audioContent = null,
                private readonly ?\Throwable $downloadError = null,
                private readonly ?\Closure $recordPublicationTime = null,
            ) {}

            public function getClipMetadata(string $clipUrl): ClipMetadata
            {
                return $this->clipMetadata;
            }

            public function downloadAudio(string $clipUrl): string
            {
                if ($this->downloadError !== null) {
                    throw $this->downloadError;
                }

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
                ($this->recordPublicationTime)($publicationTime);

                return $this->clipMetadataList;
            }
        };

        // Bound to YouTube and Web so the Platforms service resolves the fake for either URL type. It implements
        // SubscribablePlatform so subscribableFor() accepts it.
        $this->app->instance(YouTube::class, $platform);
        $this->app->instance(Web::class, $platform);
    }
}
