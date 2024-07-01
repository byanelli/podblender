<?php

namespace App\Apis\YouTubeData;

readonly class ChannelMetadata
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
