<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Based\Fluent\Fluent;
use Based\Fluent\Relations\Relation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feed extends Model
{
    use HasFactory, HasUuid, Fluent;

    public CarbonImmutable $created_at;
    const string COL_CREATED_AT = 'created_at';

    public int $id;
    const string COL_ID = 'id';

    public string $name;
    const string COL_NAME = 'name';

    public CarbonImmutable $updated_at;
    const string COL_UPDATED_AT = 'updated_at';

    public int $user_id;
    const string COL_USER_ID = 'user_id';

    public string $uuid;
    const string COL_UUID = 'uuid';

    public string $description;
    const string COL_DESCRIPTION = 'description';

    /**
     * @var Collection<int, AudioClip>
     * @see self::audioClips()
     */
    #[Relation]
    public Collection $audioClips;
    const string REL_AUDIO_CLIPS = 'audioClips';

    /**
     * @var Collection<int, AudioClip>
     * @see self::audioClipsFinishedProcessing()
     */
    #[Relation]
    public Collection $audioClipsFinishedProcessing;
    const string REL_AUDIO_CLIPS_FINISHED_PROCESSING = 'audioClipsFinishedProcessing';

    /**
     * @see self::user()
     */
    #[Relation]
    public User $user;
    const string REL_USER = 'user';

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function audioClips(): BelongsToMany {
        return $this->belongsToMany(AudioClip::class);
    }

    public function audioClipsFinishedProcessing(): BelongsToMany {
        return $this->audioClips()->where('processing', false);
    }
}
