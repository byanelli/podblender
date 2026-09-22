<?php

namespace App\Apis\Scrapfly;

use App\Apis\Scrapfly\Contracts\Client as ClientContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * Client for the Scrapfly scrape API. Scrapfly's ASP passes archive.is's
 * Cloudflare CAPTCHA and returns the page's HTML.
 *
 *   - archive.is takes ~50-75s through Scrapfly and intermittently drops the
 *     TCP connection, so the timeout is long and connection failures are
 *     retried.
 *   - The API key is in the query string, so cURL/Guzzle exception messages
 *     contain it. Every failure path throws a ScrapflyException whose message
 *     contains neither the URL nor the key.
 */
readonly class Client implements ClientContract
{
    private const string ENDPOINT = 'https://api.scrapfly.io/scrape';

    private const int MAX_ATTEMPTS = 3;

    private const int CONNECT_TIMEOUT = 15;

    public function __construct(
        private Factory $http,
        private ClientConfig $config,
    ) {}

    public function scrape(string $url, bool $renderJs = false): ScrapflyResult
    {
        // Only connection failures are retried. A Scrapfly-level failure
        // (success=false / non-2xx) is deterministic and propagates immediately.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->toResult($this->request($url, $renderJs), $url);
            } catch (ConnectionException) {
                // The message contains the full URL, including the API key, so
                // it is discarded.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new ScrapflyException(
                        'Scrapfly connection failed after '.self::MAX_ATTEMPTS.' attempts.'
                    );
                }
            }
        }

        // Unreachable, but PHPStan requires a return or throw here.
        throw new ScrapflyException('Scrapfly request did not complete.');
    }

    private function request(string $url, bool $renderJs): Response
    {
        return $this->http
            ->timeout($this->config->timeout)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->get(self::ENDPOINT, [
                'key'       => $this->config->apiKey,
                'url'       => $url,
                // ASP passes Cloudflare/CAPTCHA checks. It is what costs credits.
                'asp'       => 'true',
                'render_js' => $renderJs ? 'true' : 'false',
                'country'   => $this->config->country,
            ]);
    }

    private function toResult(Response $response, string $url): ScrapflyResult
    {
        // Report the status only. The URL contains the key.
        if ($response->failed()) {
            throw new ScrapflyException('Scrapfly API returned HTTP '.$response->status().'.');
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        /** @var array<string, mixed> $result */
        $result = is_array($json['result'] ?? null) ? $json['result'] : [];

        $success = (bool) ($result['success'] ?? false);

        if (! $success) {
            throw new ScrapflyException('Scrapfly reported an unsuccessful scrape.');
        }

        return new ScrapflyResult(
            content: (string) ($result['content'] ?? ''),
            finalUrl: (string) ($result['url'] ?? $url),
            statusCode: (int) ($result['status_code'] ?? 0),
            success: true,
        );
    }
}
