<?php

namespace App\Articles;

use DateTimeInterface;

/**
 * Metadata about an article that the caller already knows from somewhere other
 * than the page itself — today, from the RSS/Atom feed item that pointed at it.
 * A feed entry is publisher-authored and item-specific, so when a hint is
 * present it outranks everything the page-side cascades can dig up: a site that
 * ships dirty on-page metadata but a clean feed still gets clean articles.
 */
readonly class ArticleHints
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public ?string $title = null,
        public array $authors = [],
        public ?DateTimeInterface $publicationDate = null,
    ) {}

    public function title(): ?string
    {
        return ($this->title !== null && trim($this->title) !== '') ? trim($this->title) : null;
    }

    /**
     * @return array<int, string>
     */
    public function authors(): array
    {
        return array_values(array_filter(
            array_map(trim(...), $this->authors),
            fn (string $author): bool => $author !== '',
        ));
    }
}
