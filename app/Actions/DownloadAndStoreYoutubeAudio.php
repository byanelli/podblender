<?php

namespace App\Actions;

use App\YoutubeDownloader\Client;
use Illuminate\Filesystem\FilesystemManager;
use Ramsey\Uuid\Uuid;

class DownloadAndStoreYoutubeAudio
{
    public function __construct(
        private readonly Client $youtubeDownloader,
        private readonly FilesystemManager $storage,
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(string $url): StoredAudioResult {
        $downloadPath = $this->youtubeDownloader->downloadAudio($url);
        $storagePath = Uuid::uuid4()->toString();

        $downloadHandle = fopen($downloadPath, 'r');

        if (!$downloadHandle) {
            throw new \Exception("Couldn't open $downloadPath as resource");
        }

        $putIntoStorageResult = $this->storage->put($storagePath, $downloadHandle);

        if (!$putIntoStorageResult) {
            throw new \Exception("Couldn't store audio from $downloadPath");
        }

        $metadata = $this->youtubeDownloader->getMetadata($url);

        return new StoredAudioResult($storagePath, $metadata);
    }
}
