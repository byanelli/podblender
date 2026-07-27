<?php

namespace App\Models;

use App\Enums\AudioSourceKind;
use App\Enums\PlatformType;
use Carbon\CarbonImmutable;
use Database\Factories\AudioSourceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property PlatformType $platform_type
 * @property AudioSourceKind $kind
 * @property ?string $author_name
 * @property string $platform_url
 * @property CarbonImmutable $created_at
 * @property Collection<int, Feed> $subscribers
 * @property Collection<int, AudioClip> $audioClips
 */
class AudioSource extends Model
{
    /** @use HasFactory<AudioSourceFactory> */
    use HasFactory;

    protected $casts = [
        'platform_type' => PlatformType::class,
        'kind' => AudioSourceKind::class,
    ];

    const string COL_NAME = 'name';
    const string COL_PLATFORM_TYPE = 'platform_type';
    const string COL_PLATFORM_URL = 'platform_url';
    const string COL_KIND = 'kind';
    const string COL_AUTHOR_NAME = 'author_name';
    const string REL_SUBSCRIBERS = 'subscribers';

    /**
     * Who publishes this source.
     *
     * A channel is its own author, so its name serves. A playlist is a
     * collection rather than a person — "Select Lectures" is not an author — so
     * it carries the name of the channel that owns it, recorded when the source
     * was created.
     */
    public function authorName(): string
    {
        return $this->kind === AudioSourceKind::Playlist
            ? ($this->author_name ?? $this->name)
            : $this->name;
    }

    /**
     * @return HasMany<Feed, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Feed::class, 'subscription_id');
    }

    /**
     * @return HasMany<AudioClip, $this>
     */
    public function audioClips(): HasMany
    {
        return $this->hasMany(AudioClip::class);
    }
}
