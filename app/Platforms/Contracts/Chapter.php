<?php

namespace App\Platforms\Contracts;

use BYanelli\Roma\Response\IsArrayable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class Chapter implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public int $startSeconds,
        public string $title,
    ) {}

    /**
     * @param  array{startSeconds: int, title: string}  $array
     */
    public static function fromArray(array $array): self
    {
        return new self($array['startSeconds'], $array['title']);
    }
}
