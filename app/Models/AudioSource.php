<?php

namespace App\Models;

use App\Enums\PlatformType;
use Based\Fluent\Fluent;
use Based\Fluent\Relations\Relation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioSource extends Model
{
    /** @use HasFactory<\Database\Factories\AudioSourceFactory> */
    use Fluent, HasFactory;

    public CarbonImmutable $created_at;
    const string COL_CREATED_AT = 'created_at';

    public int $id;
    const string COL_ID = 'id';

    public string $name;
    const string COL_NAME = 'name';

    public PlatformType $platform_type;
    const string COL_PLATFORM_TYPE = 'platform_type';

    public string $platform_url;
    const string COL_PLATFORM_URL = 'platform_url';

    /**
     * @var Collection<int, Feed>
     *
     * @see self::subscribers()
     */
    #[Relation]
    public Collection $subscribers;
    const string REL_SUBSCRIBERS = 'subscribers';

    /**
     * @var Collection<int, AudioClip>
     *
     * @see self::audioClips()
     */
    #[Relation]
    public Collection $audioClips;
    const string REL_AUDIO_CLIPS = 'audioClips';

    public function subscribers(): HasMany
    {
        return $this->hasMany(Feed::class, 'subscription_id');
    }

    public function audioClips(): HasMany
    {
        return $this->hasMany(AudioClip::class);
    }
}
