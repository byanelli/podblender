<?php

namespace App\Models;

use App\Enums\ClipProcessingState;
use App\Models\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\FeedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property int $id
 * @property string $name
 * @property string $uuid
 * @property ?string $description
 * @property int $user_id
 * @property ?int $subscription_id
 * @property ?CarbonImmutable $subscribed_at
 * @property ?CarbonImmutable $backfill_since
 * @property bool $tracks_new_episodes
 * @property ?CarbonImmutable $subscription_filled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Collection<int, AudioClip> $audioClips
 * @property Collection<int, AudioClip> $audioClipsFinishedProcessing
 * @property User $user
 * @property ?AudioSource $subscription
 * @property string $author_name
 */
class Feed extends Model
{
    /** @use HasFactory<FeedFactory> */
    use HasFactory;

    use HasUuid;

    protected $casts = [
        'subscribed_at'          => 'datetime',
        'backfill_since'         => 'datetime',
        'subscription_filled_at' => 'datetime',
        'tracks_new_episodes'    => 'boolean',
    ];

    /**
     * The column default alone isn't enough: a freshly made model doesn't know
     * about it until it's been reloaded, so wantsFutureEpisodes() would read
     * null — and treat a brand-new subscription as one that has finished.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tracks_new_episodes' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<AudioClip, $this, AudioClipFeed>
     */
    public function audioClips(): BelongsToMany
    {
        return $this->belongsToMany(AudioClip::class)
            ->using(AudioClipFeed::class)
            ->withPivot('published_at');
    }

    /**
     * @return BelongsToMany<AudioClip, $this, AudioClipFeed>
     */
    public function audioClipsFinishedProcessing(): BelongsToMany
    {
        return $this->audioClips()
            ->where('processing_state', ClipProcessingState::Processed)
            // Newest episode first, in the order the feed itself presents them (the pivot date, not the clip's own
            // publication date). Without this the RSS emits clips in insert order, which is not the order a podcast
            // app expects and not the order ShowFeed renders them in.
            ->orderByPivot('published_at', 'desc');
    }

    /**
     * @return BelongsTo<AudioSource, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AudioSource::class, 'subscription_id');
    }

    /**
     * Does this subscriber still need its source checked?
     *
     * True while it's collecting new episodes, and also true for a one-shot
     * that hasn't been filled yet — that feed still needs the single sweep that
     * populates it. Only once a one-shot has had that fill is there nothing
     * left to do: it captured the source as it stood and asked to be left
     * alone, and checking it again would cost platform quota forever.
     */
    public function needsUpdating(): bool
    {
        return $this->tracks_new_episodes || is_null($this->subscription_filled_at);
    }

    /**
     * Restrict a query to subscribers that still need their source checked.
     * Mirrors needsUpdating() in SQL, for the sweep to select sources by.
     *
     * @param  Builder<Feed>|Relation<Feed, Model, *>  $query
     */
    public static function scopeNeedingUpdates(Builder|Relation $query): void
    {
        $query->where(
            fn (Builder $feeds) => $feeds
                ->where('tracks_new_episodes', true)
                ->orWhereNull('subscription_filled_at')
        );
    }

    /**
     * The earliest a clip can have been published and still belong in this
     * feed: whatever backfill the subscriber asked for, or failing that the
     * moment they subscribed.
     */
    public function earliestWantedPublicationTime(): ?\DateTimeInterface
    {
        return $this->backfill_since ?? $this->subscribed_at;
    }

    /**
     * Who the podcast is "by".
     *
     * For a subscription that's whoever publishes it, not the podblender user
     * who set the feed up — a listener seeing this in their podcast app expects
     * the channel's name. A hand-built feed has no such publisher, so it falls
     * back to its owner.
     *
     * @return Attribute<string, never>
     */
    public function authorName(): Attribute
    {
        return Attribute::make(
            fn (): string => is_null($this->subscription)
                ? $this->user->name
                : $this->subscription->author_name,
        );
    }

    public function markFilled(): void
    {
        $this->subscription_filled_at = CarbonImmutable::now();
        $this->save();
    }
}
