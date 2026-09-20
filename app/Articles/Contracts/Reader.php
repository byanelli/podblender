<?php

namespace App\Articles\Contracts;

use App\Articles\Article;

interface Reader
{
    /**
     * Return the Article for a URL, retrying through the archives when the
     * direct page is paywalled.
     */
    public function read(string $url): Article;
}
