<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YoutubeChannel extends Model
{
    use HasFactory;

    const COL_PLATFORM_ID = 'platform_id';
    const COL_NAME = 'name';

    public function videos() {
        return $this->hasMany(YoutubeVideo::class);
    }
}
