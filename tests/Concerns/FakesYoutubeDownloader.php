<?php

namespace Tests\Concerns;

use App\YoutubeDownloader\Client;
use App\YoutubeDownloader\Metadata;
use Illuminate\Support\Facades\Process;
use Ramsey\Uuid\Uuid;

trait FakesYoutubeDownloader
{
    protected function fakeYoutubeDownloader(
        ?string $downloadPath=null,
        ?string $downloadContents=null,
        ?Metadata $metadata=null,
    ): void {
        $downloadPath ??= sys_get_temp_dir().'/'.Uuid::uuid4()->toString();
        $downloadContents ??= Uuid::uuid4()->toString();
        $metadata ??= new Metadata(
            id: 'iwjeflijwelf',
            title: 'iwjeflijwelf',
            description: 'iwjeflijwelf',
            channel_id: 'lwiejwleif',
            channel: 'lwiejlwiejf',
            duration: 100,
        );

        $this->app->bind(Client::class, function () use ($downloadContents, $downloadPath, $metadata) {
            return new class (
                $downloadPath,
                $downloadContents,
                $metadata,
            ) extends Client {
                public function __construct(
                    private readonly string $downloadPath,
                    private readonly string $downloadContents,
                    private readonly Metadata $metadata,
                ) {
                    parent::__construct(app(), app()->make(\App\Cache\Metadata::class), Process::fake());
                }

                public function downloadAudio(string $url): string {
                    file_put_contents($this->downloadPath, $this->downloadContents);

                    return $this->downloadPath;
                }

                public function getMetadata(string $url): Metadata {
                    return $this->metadata;
                }
            };
        });
    }
}
