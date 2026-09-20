<?php

namespace App\Articles;

use RuntimeException;

/**
 * The Wayback Machine had no usable snapshot for the URL: its availability API
 * reported none, or a request to it failed.
 *
 * The Reader catches this and continues to archive.is, so it is a separate
 * class from the archive.is exceptions.
 */
class WaybackSnapshotNotFoundException extends RuntimeException {}
