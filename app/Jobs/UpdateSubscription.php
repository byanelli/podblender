<?php

namespace App\Jobs;

use App\Actions\FindOrCreateAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Exceptions\PlatformNotSubscribableException;
use App\Platforms\Platforms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use RuntimeException;

class UpdateSubscription implements ShouldBeUnique, ShouldQueue
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
     * One update per source at a time. The scheduler fires UpdateAllSubscriptions every two hours, and a slow update
     * can still be running when the next tick arrives; without this, two jobs would fetch and attach the same source's
     * clips concurrently, racing on clip creation (see FindOrCreateAudioClip) and doing the same work twice. Keyed on
     * the source rather than the job's arguments so an init job for one subscriber and a full sweep still collapse to
     * one at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->subscription->id;
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
        $subscribers = (! is_null($this->subscriber)
            ? collect([$this->subscriber])
            : $this->subscription->subscribers()->get())
            // Only update the subscribers that continue to request updates. Subscribers *must be filtered early* to
            // avoid redundant fetching from the platform.
            ->filter(fn (Feed $subscriber) => $subscriber->needsUpdating())
            ->values();

        // Nothing left for anyone: don't spend a platform request working that
        // out clip by clip.
        if ($subscribers->isEmpty()) {
            return;
        }

        // If we're backfilling, fetch all clips since the backfill time; otherwise fetch since the earliest time
        // specified by one of our subscribers.
        $earliestPublicationTime = $this->backfillSince ?: $this->earliestPublicationTimeForAll($subscribers);

        $platform = $platforms->subscribableFor($this->subscription->platform_type);

        // Download metadata for all new clips.
        $newClipMetadata = $platform->getMetadataForAllClipsPublishedSince(
            $this->subscription->platform_url,
            $earliestPublicationTime,
        );

        // Either find existing AudioClip records based on the metadata or create new ones. FindOrCreateAudioClip
        // will dispatch jobs to download audio clips.
        /** @var Collection<int, AudioClip> $newClips */
        $newClips = collect($newClipMetadata)
            ->filter(fn (ClipMetadata $clipMetadata) => $clipMetadata->publishedAt >= $earliestPublicationTime)
            ->map(fn (ClipMetadata $metadata) => $findOrCreateAudioClip->__invoke($this->subscription->platform_type, $metadata));

        $this->subscription->load('subscribers');

        // For each feed subscribed to this audio source...
        foreach ($subscribers as $subscriber) {
            // Find all clips that should be attached to this feed.
            $clipsToAttach = $newClips->where(
                'published_at',
                '>=',
                $this->earliestPublicationTimeFor($subscriber),
            );

            // Attach all clips that aren't already attached. A subscription presents a clip at the date the platform
            // published it: that's what makes a series of lectures play in the order they were given, however long
            // after the fact they were downloaded.
            $subscriber->audioClips()->syncWithoutDetaching(
                $clipsToAttach
                    ->mapWithKeys(fn (AudioClip $clip) => [
                        $clip->id => ['published_at' => $clip->published_at],
                    ])
                    ->all()
            );

            // If a subscriber doesn't want new episodes, mark it as filled.
            if (! $subscriber->tracks_new_episodes) {
                $subscriber->markFilled();
            }
        }
    }

    /**
     * How far back to fetch clips from the source. Everything published after this time is potentially new and gets
     * fetched, created (upserted), and offered to each subscriber; the per-subscriber filter in handle() then decides
     * which of them actually belong in each feed.
     *
     * The cursor is the OLDEST point any subscriber still needs covered: the minimum, over the subscribers with work
     * outstanding, of the publication date of that subscriber's newest attached clip (or, if it has none, the backfill
     * it asked for).
     *
     * @param  Collection<int, Feed>  $subscribers
     */
    private function earliestPublicationTimeForAll(Collection $subscribers): \DateTimeInterface
    {
        /** @var \DateTimeInterface $earliest */
        $earliest = $subscribers
            ->map(function (Feed $subscriber) {
                $subscriber->loadMissing('audioClips');

                return $subscriber->audioClips->isNotEmpty()
                    ? $subscriber->audioClips->max(fn (AudioClip $clip) => $clip->published_at)
                    : $subscriber->earliestWantedPublicationTime();
            })
            ->min();

        return $earliest;
    }

    /**
     * The earliest a clip can have been published and still belong in this subscriber's feed. Ordinarily that's the
     * backfill the subscriber asked for when they subscribed — reaching back a month, a decade, or to the beginning,
     * as they chose — and failing that, the moment they subscribed. A backfill passed to this job overrides both:
     * it's an explicit request to reach back to a fixed date regardless of what anyone chose earlier. Without this,
     * a backfill creates the clips and then attaches none of them, because they were all published before anyone
     * subscribed.
     */
    private function earliestPublicationTimeFor(Feed $subscriber): ?\DateTimeInterface
    {
        return $this->backfillSince ?: $subscriber->earliestWantedPublicationTime();
    }
}
