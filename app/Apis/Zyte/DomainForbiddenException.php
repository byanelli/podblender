<?php

namespace App\Apis\Zyte;

use App\Apis\Scraping\ScraperException;

/**
 * Zyte refuses to fetch this domain (HTTP 451, /download/domain-forbidden).
 * Zyte publishes no list, so the client remembers each domain it learns.
 */
class DomainForbiddenException extends ScraperException {}
