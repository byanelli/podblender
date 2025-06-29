<?php

namespace App\Models;

use App\Enums\PlatformType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property PlatformType $platform_type
 * @property string $platform_url
 * @property CarbonImmutable $created_at
 * @property Collection<int, Feed> $subscribers
 * @property Collection<int, AudioClip> $audioClips
 */
class AudioSource extends Model
{
    /** @use HasFactory<\Database\Factories\AudioSourceFactory> */
    use HasFactory;

    protected $casts = [
        'platform_type' => PlatformType::class,
    ];

    const string COL_NAME = 'name';
    const string COL_PLATFORM_TYPE = 'platform_type';
    const string COL_PLATFORM_URL = 'platform_url';
    const string REL_SUBSCRIBERS = 'subscribers';

    public function subscribers(): HasMany
    {
        return $this->hasMany(Feed::class, 'subscription_id');
    }

    public function audioClips(): HasMany
    {
        return $this->hasMany(AudioClip::class);
    }
}
