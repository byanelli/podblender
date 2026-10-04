<?php

namespace App\Articles\Contracts;

use App\Articles\ArchiveBlockedException;
use App\Articles\ArchiveSnapshotNotFoundException;
use App\Articles\GiftLinkFetchFailedException;
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
     * without Wayback's toolbar. Uses no scraper. The snapshot may be a
     * capture of the paywalled page, so the caller must check it.
     *
     * @throws WaybackSnapshotNotFoundException when no snapshot exists or the
     *                                          snapshot fetch fails
     */
    public function fetchFromWayback(string $url): string;

    /**
     * Retrieve the HTML of the newest archive.is snapshot of the URL through
     * the configured Scraper.
     *
     * @throws ArchiveSnapshotNotFoundException when the archive has no snapshot
     * @throws ArchiveBlockedException when the archive is blocked or errors
     */
    public function fetchFromArchive(string $url): string;

    /**
     * Retrieve a publisher's gift link through the configured Scraper with
     * JavaScript rendering. An expired gift link may return the paywalled
     * page, so the caller must check it.
     *
     * @throws GiftLinkFetchFailedException when the scraper fails or the
     *                                      page returns an HTTP error status
     */
    public function fetchGiftLink(string $url): string;
}
