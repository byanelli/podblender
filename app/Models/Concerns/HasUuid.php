<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * @mixin Model
 */
trait HasUuid
{
    protected string $uuidColumnName = 'uuid';

    public static function bootHasUuid()
    {
        self::creating(function (Model $model) {
            $model->setAttribute((new self)->uuidColumnName, Uuid::uuid4()->toString());
        });
    }
}
