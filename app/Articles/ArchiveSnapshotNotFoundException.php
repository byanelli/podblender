<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive listing was retrieved but has no snapshot rows, so the URL has
 * never been archived. Unlike ArchiveBlockedException, retrying won't help.
 */
class ArchiveSnapshotNotFoundException extends RuntimeException {}
