<?php

namespace App\Articles\Contracts;

interface Fetcher
{
    /**
     * Plain GET of the URL with a browser-like User-Agent.
     *
     * @throws \RuntimeException on an HTTP failure
     */
    public function fetchDirect(string $url): string;

    /**
     * Retrieve the newest archive.is snapshot of the URL, routed through the
     * residential proxy.
     *
     * @throws \RuntimeException when no snapshot exists
     */
    public function fetchFromArchive(string $url): string;
}
