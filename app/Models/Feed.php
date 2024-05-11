<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property User $user
 * @property Collection<int, AudioClip> $audioClips {@see self::audioClips()}
 * @property Collection<int, AudioClip> $audioClipsFinishedProcessing {@see self::audioClipsFinishedProcessing()}
 */
class Feed extends Model
{
    use HasFactory;

    const COL_USER_ID = 'user_id';

    /** {@see self::user()} */
    const REL_USER = 'user';
    /** {@see self::audioClips()} */
    const REL_ENTRIES = 'audioClips';
    /** {@see self::audioClipsFinishedProcessing()} */
    const REL_AUDIO_CLIPS_FINISHED_PROCESSING = 'audioClipsFinishedProcessing';

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
