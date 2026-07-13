<?php

namespace App\Apis\ArticleExtractor;

use BYanelli\Roma\Response\IsArrayable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

readonly class Article implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $url,
        public string $title,
        public string $publisher,
        public DateTimeInterface $publicationDate,
        public array $authors,
        public string $text,
    ) {}
}
