<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive lookup was BLOCKED or errored, as opposed to the snapshot being
 * genuinely absent. Raised when Scrapfly fails, reports an unsuccessful scrape,
 * or archive.is answers with a blocking HTTP status. This is a retryable
 * condition — the snapshot may well exist; we just couldn't reach it.
 */
class ArchiveBlockedException extends RuntimeException {}
