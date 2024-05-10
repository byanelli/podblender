<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property Platform $platform
 * @property string $platform_id
 * @property string $name
 */
class AudioSource extends Model
{
    use HasFactory;

    const COL_PLATFORM = 'platform';
    const COL_PLATFORM_ID = 'platform_id';
    const COL_NAME = 'name';

    protected $casts = [
        'platform' => Platform::class,
    ];
}
