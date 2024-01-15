<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Tappable;

class YoutubeVideo extends Model
{
    use HasFactory, Tappable;

    protected $casts = [
        'processing' => 'boolean',
    ];

    const COL_PLATFORM_ID = 'platform_id';
    const COL_YOUTUBE_CHANNEL_ID = 'youtube_channel_id';
    const COL_TITLE = 'title';
    const COL_DESCRIPTION = 'description';
    const COL_DURATION = 'duration';
    const COL_STORAGE_PATH = 'storage_path';
    const COL_PROCESSING = 'processing';

    public function channel() {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }
}
