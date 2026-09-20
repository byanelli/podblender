<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive lookup was blocked or failed: Scrapfly threw, reported an
 * unsuccessful scrape, or archive.is returned an HTTP error status. The
 * snapshot may still exist, so the lookup can be retried.
 */
class ArchiveBlockedException extends RuntimeException {}
