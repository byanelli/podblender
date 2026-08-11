<?php

namespace App\Models;

use App\Enums\AudioSourceType;
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
 * @property AudioSourceType $type
 * @property string $author_name
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
        'type'          => AudioSourceType::class,
    ];

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
