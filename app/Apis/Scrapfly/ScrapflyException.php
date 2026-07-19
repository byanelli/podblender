<?php

namespace App\Apis\Scrapfly;

use RuntimeException;

/**
 * A Scrapfly-level failure: the API errored, reported the scrape unsuccessful,
 * or dropped the connection past the retry budget.
 *
 * SECURITY: the Scrapfly API key travels in the request query string, so the
 * underlying cURL/Guzzle exceptions echo the full URL — key included — in their
 * messages. This exception exists so the client can rethrow a message that
 * carries NEITHER the URL NOR the key. Never seed it with a leaking message or
 * chain a leaking previous exception.
 */
class ScrapflyException extends RuntimeException {}
