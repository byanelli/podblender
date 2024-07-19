<?php

namespace App\Jobs;

use App\Actions\FindOrCreateAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\PlatformFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class InitializeSubscriptionForSubscriber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(
        private readonly AudioSource $subscription,
        private readonly Feed $subscriber,
    ) {
        // Let this run for up to 30 mins since we might need to make several API calls to get metadata.
        $this->timeout = 1800;
    }

    public function handle(
        PlatformFactory $platformFactory,
        FindOrCreateAudioClip $findOrCreateAudioClip,
    ): void {
        $platform = $platformFactory->make($this->subscription->platform_type);

        // Download metadata for all clips since the subscription date.
        $newClipMetadata = $platform->getMetadataForAllClipsPublishedSince(
            $this->subscription->platform_url,
            $this->subscriber->subscribed_at,
        );

        // Ensure all clips were published on or after the subscription date.
        $newClipMetadata = collect($newClipMetadata)
            ->where(fn(ClipMetadata $m) => $m->publishedAt >= $this->subscriber->subscribed_at)
            ->values()
            ->all();

        // Either find existing AudioClip records based on the metadata or create new ones.
        /** @var Collection<int, AudioClip> $clips */
        $clips = collect($newClipMetadata)->map(function (ClipMetadata $metadata) use ($findOrCreateAudioClip): AudioClip {
            return $findOrCreateAudioClip->__invoke($this->subscription->platform_type, $metadata);
        });

        // Attach all clips that aren't already attached.
        $this->subscriber->audioClips()->syncWithoutDetaching($clips->pluck(AudioClip::COL_ID));
    }
}
