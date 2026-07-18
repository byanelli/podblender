<?php

namespace App\Platforms\Contracts;

/**
 * A platform whose sources can be subscribed to: one that can list the clips a source has published, so that new clips
 * turn up in a feed on their own. Not every platform supports this (an arbitrary web page has no such notion), which is
 * why it's a separate contract from Platform.
 */
interface SubscribablePlatform extends Platform
{
    /**
     * @return array<int, ClipMetadata>
     */
    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array;
}
