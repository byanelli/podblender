<?php

namespace App\Models;

use App\Enums\ClipProcessingState;
use App\Models\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\FeedFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $uuid
 * @property ?string $description
 * @property int $user_id
 * @property ?int $subscription_id
 * @property ?CarbonImmutable $subscribed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Collection<int, AudioClip> $audioClips
 * @property Collection<int, AudioClip> $audioClipsFinishedProcessing
 * @property User $user
 * @property AudioSource $subscription
 */
class Feed extends Model
{
    /** @use HasFactory<FeedFactory> */
    use HasFactory;

    use HasUuid;

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];

    const string COL_NAME = 'name';
    const string COL_USER_ID = 'user_id';
    const string COL_SUBSCRIPTION_ID = 'subscription_id';
    const string COL_SUBSCRIBED_AT = 'subscribed_at';
    const string REL_AUDIO_CLIPS = 'audioClips';
    const string REL_AUDIO_CLIPS_FINISHED_PROCESSING = 'audioClipsFinishedProcessing';
    const string REL_USER = 'user';
    const string REL_SUBSCRIPTION = 'subscription';

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
            ->withPivot(AudioClipFeed::COL_PUBLISHED_AT);
    }

    /**
     * @return BelongsToMany<AudioClip, $this, AudioClipFeed>
     */
    public function audioClipsFinishedProcessing(): BelongsToMany
    {
        return $this->audioClips()
            ->where(AudioClip::COL_PROCESSING_STATE, ClipProcessingState::Processed)
            // Newest episode first, in the order the feed itself presents them (the pivot date, not the clip's own
            // publication date). Without this the RSS emits clips in insert order, which is not the order a podcast
            // app expects and not the order ShowFeed renders them in.
            ->orderByPivot(AudioClipFeed::COL_PUBLISHED_AT, 'desc');
    }

    /**
     * @return BelongsTo<AudioSource, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AudioSource::class, 'subscription_id');
    }
}
