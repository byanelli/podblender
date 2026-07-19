<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive listing came back clean but held NO snapshot rows, so the URL has
 * genuinely never been archived. Distinct from ArchiveBlockedException: there is
 * nothing to retry for — the snapshot simply does not exist.
 */
class ArchiveSnapshotNotFoundException extends RuntimeException {}
