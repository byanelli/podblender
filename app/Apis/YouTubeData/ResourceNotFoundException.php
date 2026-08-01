<?php

namespace App\Apis\YouTubeData;

/**
 * YouTube answered, but with nothing: the id doesn't exist, or the resource is
 * private or deleted. The API reports this as an empty "items" list and a 200,
 * not as an error, so it has to be turned into one — otherwise the caller reads
 * past the end of the list and fails with something unrelated to the cause.
 */
class ResourceNotFoundException extends \RuntimeException
{
    public static function for(string $resource, string $id): self
    {
        return new self("No $resource found on YouTube for \"$id\". It may be private, deleted, or the link may be wrong.");
    }
}
