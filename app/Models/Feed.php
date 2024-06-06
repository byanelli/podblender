<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Columns:
 * @property \DateTimeInterface $created_at
 * @property int $id
 * @property string $name
 * @property \DateTimeInterface $updated_at
 * @property int $user_id
 * @property string $uuid
 *
 * Relations:
 * @property Collection<int, AudioClip> $audioClips {@see self::audioClips()}
 * @property Collection<int, AudioClip> $audioClipsFinishedProcessing {@see self::audioClipsFinishedProcessing()}
 * @property User $user {@see self::user()}
 */
class Feed extends Model
{
    use HasFactory, HasUuid;

    const string COL_CREATED_AT = 'created_at';
    const string COL_ID = 'id';
    const string COL_NAME = 'name';
    const string COL_UPDATED_AT = 'updated_at';
    const string COL_USER_ID = 'user_id';
    const string COL_UUID = 'uuid';

    const string REL_AUDIO_CLIPS = 'audioClips';
    const string REL_AUDIO_CLIPS_FINISHED_PROCESSING = 'audioClipsFinishedProcessing';
    const string REL_USER = 'user';

    // todo: add to database
    public string $description = 'Lorem ipsum dolor sit amet.';
    public string $imageUrl = 'https://placehold.co/400'; // todo

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
