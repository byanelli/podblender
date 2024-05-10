<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property Collection<int, AudioClip> $audioClips {@see self::audioClips()}
 * @property Collection<int, AudioClip> $audioClipsFinishedProcessing {@see self::audioClipsFinishedProcessing()}
 */
class Feed extends Model
{
    use HasFactory;

    /** {@see self::audioClips()} */
    const REL_ENTRIES = 'audioClips';
    /** {@see self::audioClipsFinishedProcessing()} */
    const REL_AUDIO_CLIPS_FINISHED_PROCESSING = 'audioClipsFinishedProcessing';

    // todo: feed should belong to user
    public string $authorName = 'Bill Yanelli';
    public string $authorEmail = 'bill.yanelli@gmail.com';

    // todo: add to database
    public string $description = 'Lorem ipsum dolor sit amet.';
    public string $imageUrl = 'https://placehold.co/400'; // todo

    public function audioClips(): BelongsToMany {
        return $this->belongsToMany(AudioClip::class);
    }

    public function audioClipsFinishedProcessing(): BelongsToMany {
        return $this->audioClips()->where('processing', false);
    }
}
