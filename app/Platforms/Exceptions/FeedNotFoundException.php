<?php

namespace App\Platforms\Exceptions;

/**
 * The URL given for an RSS subscription was neither a feed nor an HTML page
 * that advertises one.
 */
class FeedNotFoundException extends \Exception
{
    public function __construct(string $url)
    {
        parent::__construct("No RSS or Atom feed found at $url");
    }
}
