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
     * The publication time the subscription sync last asked the platform for.
     * This is the fetch cursor, and getting it wrong is expensive but invisible
     * — too far back re-fetches history on every sweep, too recent silently
     * skips clips — so tests assert on it directly.
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
                // Let a test make the download itself fail, to exercise the job's error handling.
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

        // Bind the fake against every concrete platform so the Platforms service resolves it from the container no
        // matter which type a URL maps to. It's a SubscribablePlatform so subscribableFor() accepts it too.
        $this->app->instance(YouTube::class, $platform);
        $this->app->instance(Web::class, $platform);
    }
}
