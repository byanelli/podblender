<?php

namespace App\Jobs;

use App\Models\AudioClip;
use App\YoutubeDownloader\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DownloadAndStoreYoutubeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly AudioClip $clip) {}

    /**
     * Execute the job.
     */
    public function handle(
        Client $youtubeDownloader,
        Application $app,
        Filesystem $storage,
    ): void {
        $downloadPath = $youtubeDownloader->downloadAudio($this->clip->platform_id);

        try {
            $downloadHandle = fopen($downloadPath, 'r');

            if (!$downloadHandle) {
                throw new \Exception("Couldn't open $downloadPath as resource");
            }

            $storageResult = $storage->put($this->clip->storage_path, $downloadHandle);

            if (!$storageResult) {
                throw new \Exception("Couldn't store audio from $downloadPath");
            }

            $this->clip->processing = false;
            $this->clip->size = $storage->size($this->clip->storage_path);
            $this->clip->save();
        } finally {
            unlink($downloadPath);
        }
    }
}
