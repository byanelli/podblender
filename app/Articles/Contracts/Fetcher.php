<?php

namespace App\Articles\Contracts;

use App\Articles\ArchiveBlockedException;
use App\Articles\ArchiveSnapshotNotFoundException;
use App\Articles\WaybackSnapshotNotFoundException;

interface Fetcher
{
    /**
     * Plain GET of the URL with a browser-like User-Agent.
     *
     * @throws \RuntimeException on an HTTP failure
     */
    public function fetchDirect(string $url): string;

    /**
     * Retrieve the closest Wayback Machine snapshot of the URL as raw HTML,
     * without Wayback's toolbar. Uses no Scrapfly credits. The snapshot may be a
     * capture of the paywalled page, so the caller must check it.
     *
     * @throws WaybackSnapshotNotFoundException when no snapshot exists or the
     *                                          snapshot fetch fails
     */
    public function fetchFromWayback(string $url): string;

    /**
     * Retrieve the HTML of the newest archive.is snapshot of the URL through
     * Scrapfly's ASP.
     *
     * @throws ArchiveSnapshotNotFoundException when the listing has no snapshot
     * @throws ArchiveBlockedException when the archive is blocked or errors
     */
    public function fetchFromArchive(string $url): string;
}
