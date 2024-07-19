<?php

namespace App\Apis\ArticleExtractor;

use DateTimeInterface;

readonly class Article
{
    public function __construct(
        public string $url,
        public string $title,
        public string $publisher,
        public DateTimeInterface $publicationDate,
        public array $authors,
        public string $text,
    ) {}
}
