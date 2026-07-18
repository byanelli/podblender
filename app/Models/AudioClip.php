<?php

namespace App\Models;

use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use Carbon\CarbonImmutable;
use Database\Factories\AudioClipFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Tappable;

/**
 * @property int $id
 * @property int $audio_source_id
 * @property string $description
 * @property CarbonImmutable $published_at
 * @property int $duration
 * @property string $guid
 * @property string $platform_url
 * @property ClipProcessingState $processing_state
 * @property int $size
 * @property string $storage_path
 * @property string $title
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property AudioSource $audioSource
 * @property Collection<int, Feed> $feeds
 * @property string $audio_url {@see self::audioUrl()}
 * @property string $formatted_time {@see self::formattedTime()}
 * @property PlatformType $platform_type {@see self::platformType()}
 */
class AudioClip extends Model
{
    /** @use HasFactory<AudioClipFactory> */
    use HasFactory;

    use Tappable;

    protected $casts = [
        'processing_state' => ClipProcessingState::class,

        // Without this, published_at is a string, and comparing it against a date — which is the whole point of the
        // column — silently evaluates to false rather than failing. Note that the @property annotation above has
        // always claimed this is a CarbonImmutable; now it is one.
        self::COL_PUBLISHED_AT => 'datetime',
    ];

    protected $with = [
        self::REL_AUDIO_SOURCE,
    ];

    const string COL_AUDIO_SOURCE_ID = 'audio_source_id';
    const string COL_CREATED_AT = 'created_at';
    const string COL_DESCRIPTION = 'description';
    const string COL_PUBLISHED_AT = 'published_at';
    const string COL_DURATION = 'duration';
    const string COL_GUID = 'guid';
    const string COL_ID = 'id';
    const string COL_PLATFORM_URL = 'platform_url';
    const string COL_PROCESSING_STATE = 'processing_state';
    const string COL_SIZE = 'size';
    const string COL_STORAGE_PATH = 'storage_path';
    const string COL_TITLE = 'title';
    const string REL_AUDIO_SOURCE = 'audioSource';
    const string REL_FEEDS = 'feeds';

    /**
     * @return BelongsTo<AudioSource, $this>
     */
    public function audioSource(): BelongsTo
    {
        return $this->belongsTo(AudioSource::class);
    }

    /**
     * @return Attribute<string, never>
     */
    public function formattedTime(): Attribute
    {
        $format = ($this->duration >= 3600) ? '%h:%I:%S' : '%i:%S';

        return Attribute::make(
            fn () => now()->diff(now()->addSeconds($this->duration))->format($format)
        );
    }

    /**
     * @return Attribute<PlatformType, never>
     */
    public function platformType(): Attribute
    {
        return Attribute::make(fn () => $this->audioSource->platform_type);
    }

    /**
     * @return Attribute<string, never>
     */
    public function audioUrl(): Attribute
    {
        return Attribute::make(fn () => url(Storage::url($this->storage_path)));
    }

    /**
     * @return BelongsToMany<Feed, $this>
     */
    public function feeds(): BelongsToMany
    {
        return $this->belongsToMany(Feed::class);
    }
}
