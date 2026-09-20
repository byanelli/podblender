<?php

namespace App\Apis\YouTubeData;

/**
 * The id doesn't exist, or the resource is private or deleted. The API
 * reports this as a 200 with an empty "items" list.
 */
class ResourceNotFoundException extends \RuntimeException
{
    public static function for(string $resource, string $id): self
    {
        return new self("No $resource found on YouTube for \"$id\". It may be private, deleted, or the link may be wrong.");
    }
}
