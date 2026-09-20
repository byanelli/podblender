<?php

namespace App\Platforms\Contracts;

/**
 * A platform that can list the clips a source has published, which is what a subscription needs. Separate from
 * Platform because some platforms can't: an arbitrary web page has no list of clips.
 */
interface SubscribablePlatform extends Platform
{
    /**
     * @return array<int, ClipMetadata>
     */
    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array;
}
