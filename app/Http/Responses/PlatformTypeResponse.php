<?php

namespace App\Http\Responses;

use App\Enums\PlatformType;
use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Serializes a PlatformType as {name, value}. Roma would otherwise reduce a
 * backed enum to its scalar value, so the enum is expanded here to preserve the
 * shape the frontend consumes.
 */
readonly class PlatformTypeResponse implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $name,
        public int $value,
    ) {}

    public static function fromEnum(PlatformType $type): self
    {
        return new self(
            name: $type->name,
            value: $type->value,
        );
    }
}
