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
                $result[$property->getName()] = $property->getValue($this);
            }
        }

        return $result;
    }
}
