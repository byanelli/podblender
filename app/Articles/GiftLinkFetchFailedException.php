<?php

namespace App\Articles;

use RuntimeException;

/**
 * The scraper failed, or the publisher answered with an HTTP error status,
 * when a gift link was fetched.
 */
class GiftLinkFetchFailedException extends RuntimeException {}
