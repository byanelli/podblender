<?php

namespace App\Actions;

use App\Jobs\DownloadAndStoreThumbnail;
use App\Models\AudioClip;
use App\Platforms\Contracts\ThumbnailSource;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Queues the download of a clip's artwork. Kept alongside QueueAudioClipDownload so that whatever queues a clip's
 * audio can queue its picture the same way, without knowing what either job needs.
 */
readonly class QueueThumbnailDownload
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function __invoke(AudioClip $clip, ThumbnailSource $source): void
    {
        $this->dispatcher->dispatch(new DownloadAndStoreThumbnail($clip, $source));
    }
}
