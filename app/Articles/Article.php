<?php

namespace App\Articles;

use BYanelli\Roma\Response\IsArrayable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class Article implements Arrayable
{
    use IsArrayable;

    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public string $url,
        public string $title,
        public string $publisher,
        public DateTimeInterface $publicationDate,
        public array $authors,
        public string $text,
    ) {}
}
