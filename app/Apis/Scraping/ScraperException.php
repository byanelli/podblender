<?php

namespace App\Apis\Scraping;

use RuntimeException;

/**
 * A scraping service reported a failure, or couldn't be reached after
 * retries.
 */
class ScraperException extends RuntimeException {}
