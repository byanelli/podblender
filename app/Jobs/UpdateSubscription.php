<?php

namespace App\Jobs;

use App\Actions\FindOrCreateAudioClip;
use App\Models\AudioClip;
use App\Models\AudioClipFeed;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Exceptions\PlatformNotSubscribableException;
use App\Platforms\Platforms;
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
        private readonly ?Feed $subscriber = null,
        private readonly ?\DateTimeInterface $backfillSince = null,
    ) {
        // Let this run for up to 30 mins since we might need to make several API calls to get metadata.
        $this->timeout = 1800;
    }

    /**
     * @throws PlatformNotSubscribableException
     */
    public function handle(
        Platforms $platforms,
        FindOrCreateAudioClip $findOrCreateAudioClip,
    ): void {
        // No point in running this job if there are no subscribers.
        if (! $this->subscription->subscribers()->exists()) {
            return;
        }

        // If a specific subscriber is provided, ensure that the subscriber is actually subscribed to this audio source.
        if (! is_null($this->subscriber)
            && ! $this->subscription->subscribers()->whereKey($this->subscriber->id)->exists()
        ) {
            throw new RuntimeException('The provided subscriber is not subscribed to this audio source.');
        }

        /** @var Collection<int, Feed> $subscribers */
        $subscribers = ! is_null($this->subscriber)
            ? collect([$this->subscriber])
            : $this->subscription->subscribers()->get();

        /** @var Feed $earliestSubscriber */
        $earliestSubscriber = $subscribers->sortBy(Feed::COL_SUBSCRIBED_AT)->first();
        $earliestSubscriber->load(Feed::REL_AUDIO_CLIPS);

        // Find the most recent clip that was already created for the subscriber that subscribed earliest. Everything
        // after that is potentially a new clip and will have to be fetched from the source and created (upserted). If
        // no subscribers have clips yet, use the subscription date of the earliest subscriber.
        $latestClipPublishedAt = $this->backfillSince ?: ($earliestSubscriber->audioClips->isNotEmpty()
            ? $earliestSubscriber->audioClips->sortByDesc(AudioClip::COL_PUBLISHED_AT)->first()->published_at
            : $earliestSubscriber->subscribed_at);

        $platform = $platforms->subscribableFor($this->subscription->platform_type);

        // Download metadata for all new clips.
        $newClipMetadata = $platform->getMetadataForAllClipsPublishedSince(
            $this->subscription->platform_url,
            $latestClipPublishedAt,
        );

        // Either find existing AudioClip records based on the metadata or create new ones. FindOrCreateAudioClip
        // will dispatch jobs to download audio clips.
        /** @var Collection<int, AudioClip> $newClips */
        $newClips = collect($newClipMetadata)
            ->filter(fn (ClipMetadata $clipMetadata) => $clipMetadata->publishedAt >= $latestClipPublishedAt)
            ->map(fn (ClipMetadata $metadata) => $findOrCreateAudioClip->__invoke($this->subscription->platform_type, $metadata));

        $this->subscription->load(AudioSource::REL_SUBSCRIBERS);

        // For each feed subscribed to this audio source...
        foreach ($subscribers as $subscriber) {
            // Find all clips that should be attached to this feed.
            $clipsToAttach = $newClips->where(
                AudioClip::COL_PUBLISHED_AT,
                '>=',
                $this->earliestPublicationTimeFor($subscriber),
            );

            // Attach all clips that aren't already attached. A subscription presents a clip at the date the platform
            // published it: that's what makes a series of lectures play in the order they were given, however long
            // after the fact they were downloaded.
            $subscriber->audioClips()->syncWithoutDetaching(
                $clipsToAttach
                    ->mapWithKeys(fn (AudioClip $clip) => [
                        $clip->id => [AudioClipFeed::COL_PUBLISHED_AT => $clip->published_at],
                    ])
                    ->all()
            );
        }
    }

    /**
     * The earliest a clip can have been published and still belong in this subscriber's feed. Ordinarily that's the
     * moment they subscribed: a channel's entire back catalogue turning up in someone's podcast app the day they
     * subscribe isn't what they asked for. Backfilling is the explicit request for exactly that, so it overrides the
     * subscription date. Without this, a backfill creates the clips and then attaches none of them, because they were
     * all published before anyone subscribed.
     */
    private function earliestPublicationTimeFor(Feed $subscriber): ?\DateTimeInterface
    {
        return $this->backfillSince ?: $subscriber->subscribed_at;
    }
}
