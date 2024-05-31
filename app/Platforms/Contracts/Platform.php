<?php

namespace App\Platforms\Contracts;

use App\Platforms\Metadata;

interface Platform
{
    public function getIdFromUrl(string $url): string;

    public function getUrlFromId(string $id): string;

    public function getMetadata(string $id): Metadata;

    public function downloadAudio(string $id): string;
}
