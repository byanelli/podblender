<?php

namespace App\Apis\ArticleExtractor;

use App\Enums\IsArrayable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use ReflectionObject;

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
