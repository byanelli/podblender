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
use Carbon\CarbonImmutable;
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
        $subscribers = ! is_null($this->subscriber)
            ? collect([$this->subscriber])
            : $this->subscription->subscribers()->get();

        $latestClipPublishedAt = $this->fetchCursorFor($subscribers);

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

            $this->markFilledIfOneShot($subscriber);
        }
    }

    /**
     * A subscriber that doesn't want future episodes gets exactly one fill: it
     * captures the source as it stands now. Recording that it's had it is what
     * takes it out of the sweep — and out of the fetch cursor, which would
     * otherwise be dragged back to this feed's backfill date forever.
     */
    private function markFilledIfOneShot(Feed $subscriber): void
    {
        if ($subscriber->tracks_new_episodes || ! is_null($subscriber->subscription_filled_at)) {
            return;
        }

        $subscriber->subscription_filled_at = CarbonImmutable::now();
        $subscriber->save();
    }

    /**
     * How far back to fetch clips from the source. Everything published after this cursor is potentially new and gets
     * fetched, created (upserted), and offered to each subscriber; the per-subscriber filter in handle() then decides
     * which of them actually belong in each feed.
     *
     * The cursor is the OLDEST point any subscriber still needs covered: the minimum, over every subscriber, of the
     * publication date of that subscriber's newest attached clip (or, if it has none, the moment it subscribed). Take
     * the earliest subscriber's newest clip alone — as this used to — and a subscriber that subscribed later or whose
     * initial fill failed sits below that line forever: the fetch never reaches back to it, so it permanently misses
     * every clip published between when it subscribed and the earliest subscriber's latest clip. A backfill request
     * overrides all of this: it's an explicit ask to reach back to a fixed date regardless of who has what.
     *
     * @param  Collection<int, Feed>  $subscribers
     */
    private function fetchCursorFor(Collection $subscribers): \DateTimeInterface
    {
        if ($this->backfillSince) {
            return $this->backfillSince;
        }

        /** @var \DateTimeInterface $cursor */
        $cursor = $subscribers
            // A subscriber that has had its one fill is done, and its backfill
            // date is history. Leaving it in would peg the cursor at that date
            // forever — a single "everything since 2014" one-shot would make
            // every future sweep of this source re-fetch a decade of clips. An
            // unfilled one still counts: its fill hasn't happened yet.
            ->filter(fn (Feed $subscriber) => $subscriber->needsUpdating())
            ->map(function (Feed $subscriber) {
                $subscriber->loadMissing(Feed::REL_AUDIO_CLIPS);

                return $subscriber->audioClips->isNotEmpty()
                    ? $subscriber->audioClips->max(fn (AudioClip $clip) => $clip->published_at)
                    : $subscriber->earliestWantedPublicationTime();
            })
            ->min()
            // Every subscriber is a filled one-shot: there's nothing new anyone
            // wants, so fetch nothing rather than the whole back catalogue.
            ?? CarbonImmutable::now();

        return $cursor;
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
