<?php

namespace App\Apis\Scrapfly;

use App\Apis\Scrapfly\Contracts\Client as ClientContract;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * Thin wrapper over the Scrapfly scrape API. Scrapfly's ASP is what reliably
 * clears archive.is's Cloudflare CAPTCHA and returns the real HTML.
 *
 * Two behaviours matter here:
 *   - archive.is is SLOW through Scrapfly (~50-75s) and intermittently drops
 *     the TCP connection, so we use a generous timeout and retry connection
 *     failures a few times.
 *   - the API key rides in the query string, so a leaked cURL/Guzzle message
 *     would expose it. Every failure path rethrows a sanitized ScrapflyException
 *     whose message contains neither the URL nor the key.
 */
readonly class Client implements ClientContract
{
    private const string ENDPOINT = 'https://api.scrapfly.io/scrape';

    private const int MAX_ATTEMPTS = 3;

    private const int CONNECT_TIMEOUT = 15;

    public function __construct(
        private Factory $http,
        private Config $config,
    ) {}

    public function scrape(string $url, bool $renderJs = false): ScrapflyResult
    {
        // Retry only the transient connection drops; a Scrapfly-level failure
        // (success=false / non-2xx) is deterministic and propagates immediately.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->toResult($this->request($url, $renderJs), $url);
            } catch (ConnectionException|RequestException) {
                // Swallow the message on purpose: it contains the full URL and
                // therefore the API key. Never surface or chain it.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new ScrapflyException(
                        'Scrapfly connection failed after '.self::MAX_ATTEMPTS.' attempts.'
                    );
                }
            }
        }

        // Unreachable: the loop either returns or throws, but keeps the analyser happy.
        throw new ScrapflyException('Scrapfly request did not complete.');
    }

    private function request(string $url, bool $renderJs): Response
    {
        return $this->http
            ->timeout((int) $this->config->get('articles.scrapfly_timeout', 180))
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->get(self::ENDPOINT, [
                'key'       => (string) $this->config->get('services.scrapfly.key'),
                'url'       => $url,
                // ASP clears Cloudflare/CAPTCHA. This is what costs the credits.
                'asp'       => 'true',
                'render_js' => $renderJs ? 'true' : 'false',
                'country'   => (string) $this->config->get('articles.scrapfly_country', 'us'),
            ]);
    }

    private function toResult(Response $response, string $url): ScrapflyResult
    {
        // A non-2xx from Scrapfly itself is a Scrapfly-level failure. Report the
        // status only — never the URL, which carries the key.
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
