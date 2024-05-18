<?php

namespace App\Apis\ArticleExtractor;

readonly class Article
{
    public function __construct(
        public string $url,
        public string $title,
        public string $publisher,
        public array $authors,
        public string $text,
    ) {}
}
