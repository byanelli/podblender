<?php

namespace App\Articles\Contracts;

use App\Articles\ArchiveBlockedException;
use App\Articles\ArchiveSnapshotNotFoundException;

interface Fetcher
{
    /**
     * Plain GET of the URL with a browser-like User-Agent.
     *
     * @throws \RuntimeException on an HTTP failure
     */
    public function fetchDirect(string $url): string;

    /**
     * Retrieve the newest archive.is snapshot of the URL via Scrapfly's ASP:
     * fetch the snapshot listing, parse the newest snapshot, and return its HTML.
     *
     * @throws ArchiveSnapshotNotFoundException when the listing holds no snapshot
     * @throws ArchiveBlockedException when the archive is blocked or errors
     */
    public function fetchFromArchive(string $url): string;
}
