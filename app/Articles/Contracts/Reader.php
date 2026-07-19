<?php

namespace App\Articles\Contracts;

use App\Articles\Article;
use App\Articles\ArticleHints;

interface Reader
{
    /**
     * Fetch, extract, and return the Article for a URL, retrying through
     * archive.is when the direct page is gated. Hints carry metadata the
     * caller already knows (e.g. from an RSS feed item); a present hint
     * outranks whatever the page itself says.
     */
    public function read(string $url, ?ArticleHints $hints = null): Article;
}
