<?php

namespace App\Articles;

use RuntimeException;

/**
 * The archive answered that it has no snapshot of the URL. Unlike ArchiveBlockedException, retrying won't help.
 */
class ArchiveSnapshotNotFoundException extends RuntimeException {}
