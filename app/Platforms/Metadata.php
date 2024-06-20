<?php

namespace App\Platforms;

use Illuminate\Contracts\Support\Arrayable;

readonly class Metadata implements Arrayable
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $sourceId,
        public string $sourceName,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sourceId' => $this->sourceId,
            'sourceName' => $this->sourceName,
        ];
    }
}
