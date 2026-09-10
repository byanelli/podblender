<?php

namespace App\Actions;

use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Queues the download of a clip's audio. Both the first attempt, when the clip is created, and a retry after a failed
 * download go through here, so that the job's throttling middleware and timeout behave the same way either time.
 */
readonly class QueueAudioClipDownload
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(AudioClip $clip): void
    {
        $this->dispatcher->dispatch(new DownloadAndStoreAudioClip($clip));
    }
}
