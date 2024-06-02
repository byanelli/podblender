<?php

namespace App\Models;

use App\Enums\PlatformType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Tappable;

/**
 * Columns:
 * @property int $audio_source_id
 * @property \DateTimeInterface $created_at
 * @property string $description
 * @property int $duration
 * @property string $guid
 * @property int $id
 * @property string $platform_url
 * @property bool $processing
 * @property int $size
 * @property string $storage_path
 * @property string $title
 * @property \DateTimeInterface $updated_at
 *
 * Relations:
 * @property AudioSource $audioSource {@see self::audioSource()}
 *
 * Attributes:
 * @property string $audio_url {@see self::audioUrl()}
 * @property string $formatted_time {@see self::formattedTime()}
 * @property PlatformType $platformType {@see self::platformType()}
 */
class AudioClip extends Model
{
    use HasFactory, Tappable;

    protected $casts = [
        'processing' => 'boolean',
    ];

    // Columns
    const COL_AUDIO_SOURCE_ID = 'audio_source_id';
    const COL_CREATED_AT = 'created_at';
    const COL_DESCRIPTION = 'description';
    const COL_DURATION = 'duration';
    const COL_GUID = 'guid';
    const COL_ID = 'id';
    const COL_PLATFORM_URL= 'platform_url';
    const COL_PROCESSING = 'processing';
    const COL_SIZE = 'size';
    const COL_STORAGE_PATH = 'storage_path';
    const COL_TITLE = 'title';

    // Relations
    const REL_AUDIO_SOURCE = 'audioSource';

    public function audioSource(): BelongsTo {
        return $this->belongsTo(AudioSource::class);
    }

    public function formattedTime(): Attribute {
        $format = ($this->duration >= 3600) ? '%h:%I:%S' : '%i:%S';

        return Attribute::make(
            fn() => now()->diff(now()->addSeconds($this->duration))->format($format)
        );
    }

    public function platformType(): Attribute {
        return Attribute::make(fn() => $this->audioSource->platform_type);
    }

    public function audioUrl(): Attribute {
        return Attribute::make(fn() => url(Storage::url($this->storage_path)));
    }
}
