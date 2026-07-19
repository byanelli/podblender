<?php

namespace App\Articles\Contracts;

use App\Articles\Article;

interface Reader
{
    /**
     * Fetch, extract, and return the Article for a URL, retrying through
     * archive.is when the direct page is gated.
     */
    public function read(string $url): Article;
}
