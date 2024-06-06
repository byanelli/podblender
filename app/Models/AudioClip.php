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
    const string COL_AUDIO_SOURCE_ID = 'audio_source_id';
    const string COL_CREATED_AT = 'created_at';
    const string COL_DESCRIPTION = 'description';
    const string COL_DURATION = 'duration';
    const string COL_GUID = 'guid';
    const string COL_ID = 'id';
    const string COL_PLATFORM_URL= 'platform_url';
    const string COL_PROCESSING = 'processing';
    const string COL_SIZE = 'size';
    const string COL_STORAGE_PATH = 'storage_path';
    const string COL_TITLE = 'title';

    // Relations
    const string REL_AUDIO_SOURCE = 'audioSource';

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
