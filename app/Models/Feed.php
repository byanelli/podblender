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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $uuid
 * @property ?string $description
 * @property ?string $cover_path
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
 * @property ?string $cover_url {@see self::coverUrl()}
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
     * Duplicates the column default, which a new model doesn't have until it
     * is reloaded. Without it tracks_new_episodes reads as null on a feed that
     * was just created.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tracks_new_episodes' => true,
    ];

    protected $appends = [
        'cover_url',
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
            // Newest first by the pivot date, which is the date the feed presents the clip at. The RSS and ShowFeed
            // both depend on this order.
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
     * Whether this feed's source still needs checking: true while the feed
     * tracks new episodes, and for a one-time feed until its first fill.
     * Checking a filled one-time feed would only spend platform quota.
     */
    public function needsUpdating(): bool
    {
        return $this->tracks_new_episodes || is_null($this->subscription_filled_at);
    }

    /**
     * The SQL equivalent of needsUpdating().
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
     * Clips published before this time are left out of the feed.
     */
    public function earliestWantedPublicationTime(): ?\DateTimeInterface
    {
        return $this->backfill_since ?? $this->subscribed_at;
    }

    /**
     * The podcast's author as shown in podcast apps: the source's publisher
     * for a subscription, the feed's user for a custom feed.
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

    /**
     * The feed's artwork, or null if it has none. The feed page and the RSS
     * channel then show no image, as with {@see AudioClip::thumbnailUrl()}.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            fn () => $this->cover_path === null
                ? null
                : url(Storage::url($this->cover_path))
        );
    }

    public function markFilled(): void
    {
        $this->subscription_filled_at = CarbonImmutable::now();
        $this->save();
    }
}
