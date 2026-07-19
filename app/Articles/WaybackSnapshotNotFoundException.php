<?php

namespace App\Articles;

use RuntimeException;

/**
 * The Wayback Machine had no usable snapshot for the URL — either its
 * availability API reported none, or the snapshot fetch itself hiccuped.
 *
 * Wayback is a free, best-effort middle tier between a direct fetch and the
 * paid archive.is backstop; a miss here is never fatal, it just falls the read
 * through to archive.is. Distinct from the archive.is exceptions so the Reader
 * can catch a Wayback miss specifically and keep going.
 */
class WaybackSnapshotNotFoundException extends RuntimeException {}
