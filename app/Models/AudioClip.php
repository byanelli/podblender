<?php

namespace App\Models;

use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Support\AudioPreview;
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
 * @property int|null $estimated_download_time
 * @property string $guid
 * @property string $platform_url
 * @property ClipProcessingState $processing_state
 * @property int $size
 * @property string $storage_path
 * @property string|null $thumbnail_path
 * @property string $title
 * @property string|null $tts_model
 * @property int|null $tts_input_tokens
 * @property int|null $tts_output_tokens
 * @property float|null $tts_cost USD
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property AudioSource $audioSource
 * @property Collection<int, Feed> $feeds
 * @property string $audio_url {@see self::audioUrl()}
 * @property string|null $preview_url {@see self::previewUrl()}
 * @property string|null $thumbnail_url {@see self::thumbnailUrl()}
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

        // Uncast, published_at is a string, and comparing it against a date evaluates to false without an error.
        'published_at'     => 'datetime',

        'tts_cost'         => 'float',
    ];

    protected $with = [
        'audioSource',
    ];

    // Internal cost data, kept out of the feed pages' props.
    protected $hidden = [
        'tts_model',
        'tts_input_tokens',
        'tts_output_tokens',
        'tts_cost',
    ];

    protected $appends = [
        'audio_url',
        'preview_url',
        'thumbnail_url',
    ];

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
     * The clip's public URL, used for the RSS enclosure. Set on every disk,
     * because podcast clients fetch the file directly.
     *
     * @return Attribute<string, never>
     */
    protected function audioUrl(): Attribute
    {
        return Attribute::make(fn () => url(Storage::url($this->storage_path)));
    }

    /**
     * The audio URL when the browser can play it on the feed page, otherwise
     * null. {@see AudioPreview}
     *
     * @return Attribute<string|null, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::make(
            fn () => AudioPreview::available() ? $this->audio_url : null
        );
    }

    /**
     * The clip's artwork, or null if the platform offered no image or its
     * download failed. The feed page and the RSS item then show no image.
     *
     * @return Attribute<string|null, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            fn () => $this->thumbnail_path === null
                ? null
                : url(Storage::url($this->thumbnail_path))
        );
    }

    /**
     * @return BelongsToMany<Feed, $this>
     */
    public function feeds(): BelongsToMany
    {
        return $this->belongsToMany(Feed::class);
    }
}
