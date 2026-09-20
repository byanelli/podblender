<?php

namespace App\Apis\Scrapfly;

use RuntimeException;

/**
 * A Scrapfly-level failure: the API returned an error, reported the scrape
 * unsuccessful, or the connection failed on every retry.
 *
 * SECURITY: the Scrapfly API key is in the request query string, so cURL and
 * Guzzle exception messages include it. Never construct this exception from
 * such a message or pass one as $previous.
 */
class ScrapflyException extends RuntimeException {}
