<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionObject;

/**
 * @mixin Arrayable
 */
trait IsArrayable
{
    /**
     * @implements Arrayable::toArray()
     */
    public function toArray(): array
    {
        $result = [];

        $reflection = new ReflectionObject($this);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $value = $property->getValue($this);

                $result[$property->getName()] = ($value instanceof Arrayable) ? $value->toArray() : $value;
            }
        }

        return $result;
    }
}
