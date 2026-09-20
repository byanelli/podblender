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
        // If present, update only this subscriber, e.g. to fill a new subscription.
        private readonly ?Feed $subscriber = null,
        private readonly ?\DateTimeInterface $backfillSince = null,
    ) {
        // 30 minutes: fetching metadata can take several API calls.
        $this->timeout = 1800;
    }

    /**
     * One update per source at a time. UpdateAllSubscriptions is scheduled every two hours, and a slow update can still
     * be running at the next run; two jobs for one source would race on clip creation (see FindOrCreateAudioClip) and
     * repeat each other's work. The key is the source id and ignores the other arguments, so a single-subscriber job
     * and a full update of the same source also count as the same job.
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
        if (! $this->subscription->subscribers()->exists()) {
            return;
        }

        if (! is_null($this->subscriber)
            && ! $this->subscription->subscribers()->whereKey($this->subscriber->id)->exists()
        ) {
            throw new RuntimeException('The provided subscriber is not subscribed to this audio source.');
        }

        /** @var Collection<int, Feed> $subscribers */
        $subscribers = (! is_null($this->subscriber)
            ? collect([$this->subscriber])
            : $this->subscription->subscribers()->get())
            // Filter before fetching, to avoid redundant requests to the platform.
            ->filter(fn (Feed $subscriber) => $subscriber->needsUpdating())
            ->values();

        // No subscriber needs an update, so skip the platform request.
        if ($subscribers->isEmpty()) {
            return;
        }

        $earliestPublicationTime = $this->backfillSince ?: $this->earliestPublicationTimeForAll($subscribers);

        $platform = $platforms->subscribableFor($this->subscription->platform_type);

        $newClipMetadata = $platform->getMetadataForAllClipsPublishedSince(
            $this->subscription->platform_url,
            $earliestPublicationTime,
        );

        // FindOrCreateAudioClip queues the download for each clip it creates.
        /** @var Collection<int, AudioClip> $newClips */
        $newClips = collect($newClipMetadata)
            ->filter(fn (ClipMetadata $clipMetadata) => $clipMetadata->publishedAt >= $earliestPublicationTime)
            ->map(fn (ClipMetadata $metadata) => $findOrCreateAudioClip->__invoke($this->subscription->platform_type, $metadata));

        $this->subscription->load('subscribers');

        foreach ($subscribers as $subscriber) {
            $clipsToAttach = $newClips->where(
                'published_at',
                '>=',
                $this->earliestPublicationTimeFor($subscriber),
            );

            // The pivot date is the platform's publication date, so a backfilled series plays in its original order.
            $subscriber->audioClips()->syncWithoutDetaching(
                $clipsToAttach
                    ->mapWithKeys(fn (AudioClip $clip) => [
                        $clip->id => ['published_at' => $clip->published_at],
                    ])
                    ->all()
            );

            if (! $subscriber->tracks_new_episodes) {
                $subscriber->markFilled();
            }
        }
    }

    /**
     * How far back to fetch clips from the source. Clips published after this time are fetched and created, and
     * handle() then filters them for each subscriber.
     *
     * This is the minimum, over the given subscribers, of the publication date of the subscriber's newest attached
     * clip, or of its requested backfill date if it has no clips.
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
     * The earliest a clip can have been published and still belong in this subscriber's feed: the backfill date chosen
     * at subscription, or failing that the subscription date. A backfill date passed to this job overrides both;
     * otherwise the job would create the backfilled clips and attach none of them.
     */
    private function earliestPublicationTimeFor(Feed $subscriber): ?\DateTimeInterface
    {
        return $this->backfillSince ?: $subscriber->earliestWantedPublicationTime();
    }
}
