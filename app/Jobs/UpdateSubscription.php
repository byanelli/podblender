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
use RuntimeException;


class UpdateSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(
        private readonly AudioSource $subscription,
        // If present, update only one subscriber. This can be used to initialize the subscription for a given
        // subscriber.
        private readonly ?Feed $subscriber=null,
    ) {
        // Let this run for up to 30 mins since we might need to make several API calls to get metadata.
        $this->timeout = 1800;
    }

    public function handle(
        PlatformFactory $platformFactory,
        FindOrCreateAudioClip $findOrCreateAudioClip,
    ): void {
        // No point in running this job if there are no subscribers.
        if (!$this->subscription->subscribers()->exists()) { return; }

        // If a specific subscriber is provided, ensure that the subscriber is actually subscribed to this audio source.
        if (!is_null($this->subscriber)
            && !$this->subscription->subscribers()->whereKey($this->subscriber->id)->exists()
        ) {
            throw new RuntimeException('The provided subscriber is not subscribed to this audio source.');
        }

        /** @var Collection<int, Feed> $subscribers */
        $subscribers = !is_null($this->subscriber)
            ? collect([$this->subscriber])
            : $this->subscription->subscribers()->get();

        /** @var Feed $earliestSubscriber */
        $earliestSubscriber = $subscribers->sortBy(Feed::COL_SUBSCRIBED_AT)->first();
        $earliestSubscriber->load(Feed::REL_AUDIO_CLIPS);

        // Find the most recent clip that was already created for the subscriber that subscribed earliest. Everything
        // after that is potentially a new clip and will have to be fetched from the source and created (upserted). If
        // no subscribers have clips yet, use the subscription date of the earliest subscriber.
        $latestClipPublishedAt = $earliestSubscriber->audioClips->isNotEmpty()
            ? $earliestSubscriber->audioClips->sortByDesc(AudioClip::COL_PUBLISHED_AT)->first()->published_at
            : $earliestSubscriber->subscribed_at;

        $platform = $platformFactory->make($this->subscription->platform_type);

        // Download metadata for all new clips.
        $newClipMetadata = $platform->getMetadataForAllClipsPublishedSince(
            $this->subscription->platform_url,
            $latestClipPublishedAt,
        );

        // Either find existing AudioClip records based on the metadata or create new ones.
        /** @var Collection<int, AudioClip> $newClips */
        $newClips = collect($newClipMetadata)
            ->filter(fn(ClipMetadata $clipMetadata) => $clipMetadata->publishedAt >= $latestClipPublishedAt)
            ->map(fn(ClipMetadata $metadata) => $findOrCreateAudioClip->__invoke($this->subscription->platform_type, $metadata));

        $this->subscription->load(AudioSource::REL_SUBSCRIBERS);

        // For each feed subscribed to this audio source...
        foreach ($subscribers as $subscriber) {
            // Find all clips that should be attached to this feed, i.e., those published after the subscription date.
            $clipsToAttach = $newClips->where(AudioClip::COL_PUBLISHED_AT, '>=', $subscriber->subscribed_at);

            // Attach all clips that aren't already attached.
            $subscriber->audioClips()->syncWithoutDetaching($clipsToAttach->pluck(AudioClip::COL_ID));
        }
    }
}
