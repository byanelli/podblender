<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive fetch was blocked or failed: the scraper threw, or archive.is
 * returned an HTTP error status other than 404. The
 * snapshot may still exist, so the lookup can be retried.
 */
class ArchiveBlockedException extends RuntimeException {}
