<?php

namespace App\Http\Responses;

use BYanelli\Roma\Response\Attributes\DateFormat;
use BYanelli\Roma\Response\IsArrayable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

readonly class ClipMetadataResponse implements Arrayable
{
    use IsArrayable;

    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        // Matches the previous Carbon::jsonSerialize() output (UTC, microseconds,
        // "Z" suffix). The value is normalized to UTC in MetadataResponse::fromDomain.
        #[DateFormat('Y-m-d\TH:i:s.u\Z')]
        public DateTimeInterface $publishedAt,
        public SourceMetadataResponse $source,
    ) {}
}
