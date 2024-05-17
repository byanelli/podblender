<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns:
 * @property \DateTimeInterface $created_at
 * @property int $id
 * @property string $name
 * @property Platform $platform
 * @property string $platform_id
 */
class AudioSource extends Model
{
    use HasFactory;

    // Columns
    const COL_CREATED_AT = 'created_at';
    const COL_ID = 'id';
    const COL_NAME = 'name';
    const COL_PLATFORM = 'platform';
    const COL_PLATFORM_ID = 'platform_id';

    protected $casts = [
        'platform' => Platform::class,
    ];
}
