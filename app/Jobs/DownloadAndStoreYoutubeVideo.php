<?php

namespace App\Jobs;

use App\Models\YoutubeVideo;
use App\YoutubeDownloader\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DownloadAndStoreYoutubeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly YoutubeVideo $video) {}

    /**
     * Execute the job.
     */
    public function handle(
        Client $youtubeDownloader,
        FilesystemManager $storage,
    ): void {
        $downloadPath = $youtubeDownloader->downloadAudio($this->video->platform_id);
        $downloadHandle = fopen($downloadPath, 'r');

        if (!$downloadHandle) {
            throw new \Exception("Couldn't open $downloadPath as resource");
        }

        $storageResult = $storage->put($this->video->storage_path, $downloadHandle);

        if (!$storageResult) {
            throw new \Exception("Couldn't store audio from $downloadPath");
        }

        $this->video->processing = false;
        $this->video->save();
    }
}
