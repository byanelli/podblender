<?php

namespace App\Models;

use App\Enums\PlatformType;
use Based\Fluent\Fluent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioSource extends Model
{
    use HasFactory, Fluent;

    public CarbonImmutable $created_at;
    const string COL_CREATED_AT = 'created_at';

    public int $id;
    const string COL_ID = 'id';

    public string $name;
    const string COL_NAME = 'name';

    public PlatformType $platform_type;
    const string COL_PLATFORM_TYPE = 'platform_type';

    public string $platform_id;
    const string COL_PLATFORM_ID = 'platform_id';
}
