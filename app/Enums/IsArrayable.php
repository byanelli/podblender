<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @mixin Arrayable
 * @mixin \UnitEnum
 * @mixin \BackedEnum
 */
trait IsArrayable
{
    /**
     * @implements Arrayable::toArray()
     */
    public function toArray(): array
    {
        $result = ['name' => $this->name];

        if ($this instanceof \BackedEnum) {
            $result['value'] = $this->value;
        }

        return $result;
    }
}
