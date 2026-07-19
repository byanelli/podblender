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
     * Retrieve the raw Wayback Machine snapshot of the URL: ask the availability
     * API for the closest capture, then GET its "id_" (toolbar-free) HTML. Free
     * and Cloudflare-free (plain Guzzle, no Scrapfly), but best-effort — the
     * snapshot may itself be the paywalled capture, so the caller re-validates it.
     *
     * @throws WaybackSnapshotNotFoundException when no snapshot exists or the
     *                                          snapshot fetch fails
     */
    public function fetchFromWayback(string $url): string;

    /**
     * Retrieve the newest archive.is snapshot of the URL via Scrapfly's ASP:
     * fetch the snapshot listing, parse the newest snapshot, and return its HTML.
     *
     * @throws ArchiveSnapshotNotFoundException when the listing holds no snapshot
     * @throws ArchiveBlockedException when the archive is blocked or errors
     */
    public function fetchFromArchive(string $url): string;
}
