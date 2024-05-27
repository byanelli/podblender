<?php

namespace App\Models;

use App\Enums\PlatformType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns:
 * @property \DateTimeInterface $created_at
 * @property int $id
 * @property string $name
 * @property PlatformType $platform_type
 * @property string $platform_id
 */
class AudioSource extends Model
{
    use HasFactory;

    // Columns
    const COL_CREATED_AT = 'created_at';
    const COL_ID = 'id';
    const COL_NAME = 'name';
    const COL_PLATFORM_TYPE = 'platform_type';
    const COL_PLATFORM_ID = 'platform_id';

    protected $casts = [
        self::COL_PLATFORM_TYPE => PlatformType::class,
    ];
}
