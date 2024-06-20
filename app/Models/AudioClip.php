<?php

namespace App\Models;

use App\Enums\PlatformType;
use Based\Fluent\Fluent;
use Based\Fluent\Relations\Relation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Tappable;

/**
 * @property string $audio_url {@see self::audioUrl()}
 * @property string $formatted_time {@see self::formattedTime()}
 * @property PlatformType $platform_type {@see self::platformType()}
 */
class AudioClip extends Model
{
    use HasFactory, Tappable, Fluent;

    protected $with = [
        self::REL_AUDIO_SOURCE,
    ];

    public int $audio_source_id;
    const string COL_AUDIO_SOURCE_ID = 'audio_source_id';

    public CarbonImmutable $created_at;
    const string COL_CREATED_AT = 'created_at';

    public string $description;
    const string COL_DESCRIPTION = 'description';

    public int $duration;
    const string COL_DURATION = 'duration';

    public string $guid;
    const string COL_GUID = 'guid';

    public int $id;
    const string COL_ID = 'id';

    public string $platform_url;
    const string COL_PLATFORM_URL= 'platform_url';

    public bool $processing;
    const string COL_PROCESSING = 'processing';

    public int $size;
    const string COL_SIZE = 'size';

    public string $storage_path;
    const string COL_STORAGE_PATH = 'storage_path';

    public string $title;
    const string COL_TITLE = 'title';

    public CarbonImmutable $updated_at;
    const string COL_UPDATED_AT = 'updated_at';

    /**
     * @see self::audioSource()
     */
    #[Relation]
    public AudioSource $audioSource;
    const string REL_AUDIO_SOURCE = 'audioSource';

    /**
     * @see self::feeds()
     * @var Collection<int, Feed> $feeds
     */
    #[Relation]
    public Collection $feeds;
    const string REL_FEEDS = 'feeds';

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

    public function feeds(): BelongsToMany {
        return $this->belongsToMany(Feed::class);
    }
}
