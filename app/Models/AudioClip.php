<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Tappable;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property bool $processing
 * @property int $size
 * @property string $storage_path
 * @property string $guid
 * @property string $platform_id
 * @property int $duration
 * @property string $formatted_time {@see self::formattedTime()}
 * @property string $source_url {@see self::sourceUrl()}
 * @property string $audio_url {@see self::audioUrl()}
 * @property AudioSource $audioSource {@see self::audioSource()}
 * @property Platform $platform {@see self::platform()}
 * @property \DateTimeInterface $created_at
 * @property bool $processed
 */
class AudioClip extends Model
{
    use HasFactory, Tappable;

    protected $casts = [
        'processing' => 'boolean',
    ];

    const COL_PLATFORM_ID = 'platform_id';
    const COL_AUDIO_SOURCE_ID = 'audio_source_id';
    const COL_TITLE = 'title';
    const COL_DESCRIPTION = 'description';
    const COL_DURATION = 'duration';
    const COL_STORAGE_PATH = 'storage_path';
    const COL_GUID = 'guid';
    const COL_PROCESSING = 'processing';
    const COL_SIZE = 'size';
    const REL_AUDIO_SOURCE = 'audioSource';
    const COL_CREATED_AT = 'created_at';

    public function audioSource(): BelongsTo {
        return $this->belongsTo(AudioSource::class);
    }

    public function formattedTime(): Attribute {
        $format = ($this->duration >= 3600) ? '%h:%I:%S' : '%i:%S';

        return Attribute::make(
            fn() => now()->diff(now()->addSeconds($this->duration))->format($format)
        );
    }

    public function platform(): Attribute {
        return Attribute::make(fn() => $this->audioSource->platform);
    }

    public function sourceUrl(): Attribute {
        return Attribute::make(fn() => $this->platform->formatUrl($this->platform_id));
    }

    public function audioUrl(): Attribute {
        return Attribute::make(fn() => url(Storage::url($this->storage_path)));
    }
}
