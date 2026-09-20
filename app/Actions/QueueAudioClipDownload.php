<?php

namespace App\Actions;

use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Queues the download of a clip's audio. Used both when a clip is created and when a failed download is retried, so
 * the job is constructed the same way in each case.
 */
readonly class QueueAudioClipDownload
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(AudioClip $clip): void
    {
        $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));
    }
}
