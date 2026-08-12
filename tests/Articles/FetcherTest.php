<?php

namespace Tests\Articles;

use App\Articles\ArchiveBlockedException;
use App\Articles\ArchiveSnapshotNotFoundException;
use App\Articles\Contracts\Fetcher;
use App\Articles\WaybackSnapshotNotFoundException;
use App\Proxies\Contracts\ResidentialProxyConfig;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetcherTest extends TestCase
{
    public const PROXY_URL = 'http://residential.proxy.test:7777';

    protected function setUp(): void
    {
        parent::setUp();

        // Stub the residential proxy with a fixed URL so we can assert exactly
        // that the Wayback hops are proxied, rather than fighting the real
        // config's random session id.
        $this->app->bind(ResidentialProxyConfig::class, fn () => new readonly class implements ResidentialProxyConfig
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function getUrlForDownload(): string
            {
                return FetcherTest::PROXY_URL;
            }

            public function requiresInsecureTls(): bool
            {
                return false;
            }
        });
    }

    private function fetcher(): Fetcher
    {
        return $this->app->make(Fetcher::class);
    }

    private function listingHtml(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/archive-today-listing.html');
    }

    /**
     * A Scrapfly scrape JSON envelope wrapping the given target HTML.
     */
    private function scrapflyResponse(string $content, int $statusCode = 200, bool $success = true): PromiseInterface
    {
        return Http::response([
            'result' => [
                'content'     => $content,
                'url'         => 'https://archive.is/final',
                'status_code' => $statusCode,
                'success'     => $success,
                'cost'        => ['total' => 30, 'details' => []],
            ],
        ]);
    }

    /**
     * Route Scrapfly calls by their target `url` query param: a bare 5-char
     * archive code is the snapshot; anything else is the listing.
     *
     * @param  callable|PromiseInterface  $listing
     * @param  callable|PromiseInterface  $snapshot
     */
    private function fakeTwoStep($listing, $snapshot): void
    {
        Http::fake(function (Request $request) use ($listing, $snapshot) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $target = (string) ($query['url'] ?? '');

            $response = preg_match('~^https?://archive\.[a-z]+/[A-Za-z0-9]{5}$~', $target)
                ? $snapshot
                : $listing;

            return is_callable($response) ? $response($request) : $response;
        });
    }

    #[Test]
    public function it_fetches_a_page_directly_with_a_browser_user_agent()
    {
        Http::fake(['*' => Http::response('<html>direct</html>')]);

        $body = $this->fetcher()->fetchDirect('https://theonion.com/some-article');

        $this->assertEquals('<html>direct</html>', $body);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://theonion.com/some-article'
            && $request->hasHeader('User-Agent', config('articles.user_agent')));
    }

    #[Test]
    public function it_throws_when_a_direct_fetch_fails()
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(RequestException::class);

        $this->fetcher()->fetchDirect('https://theonion.com/some-article');
    }

    /**
     * A Wayback availability envelope reporting a closest snapshot at $timestamp.
     */
    private function waybackAvailable(string $timestamp): PromiseInterface
    {
        return Http::response([
            'archived_snapshots' => [
                'closest' => [
                    'available' => true,
                    'url'       => "http://web.archive.org/web/$timestamp/https://www.example.com/x",
                    'timestamp' => $timestamp,
                    'status'    => '200',
                ],
            ],
        ]);
    }

    #[Test]
    public function it_fetches_the_raw_id_snapshot_when_wayback_has_a_capture()
    {
        $proxied = [];

        Http::fake(function (Request $request, array $options) use (&$proxied) {
            $proxied[] = $options['proxy'] ?? null;

            return str_contains($request->url(), '/wayback/available')
                ? $this->waybackAvailable('20260115184700')
                : Http::response('<html>wayback body</html>');
        });

        $body = $this->fetcher()->fetchFromWayback('https://www.example.com/x');

        $this->assertEquals('<html>wayback body</html>', $body);

        // Both Wayback hops go out through the residential proxy — production runs
        // from a datacenter IP archive.org rate-limits and blocks.
        $this->assertCount(2, $proxied);
        $this->assertSame([self::PROXY_URL, self::PROXY_URL], $proxied);

        // The availability API is asked about the requested URL, on archive.org.
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://archive.org/wayback/available')
                && ($query['url'] ?? null) === 'https://www.example.com/x';
        });

        // The snapshot is fetched raw: web.archive.org, the response timestamp,
        // the "id_" (toolbar-free) modifier, and the original URL.
        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://web.archive.org/web/20260115184700id_/https://www.example.com/x'
            && $request->hasHeader('User-Agent', config('articles.user_agent')));
    }

    #[Test]
    public function it_throws_wayback_not_found_when_no_snapshot_is_available()
    {
        Http::fake(['*' => Http::response(['archived_snapshots' => []])]);

        $this->expectException(WaybackSnapshotNotFoundException::class);

        $this->fetcher()->fetchFromWayback('https://www.example.com/never-archived');
    }

    #[Test]
    public function it_throws_wayback_not_found_when_the_snapshot_fetch_errors()
    {
        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/wayback/available')
                ? $this->waybackAvailable('20260115184700')
                : Http::response('boom', 500);
        });

        $this->expectException(WaybackSnapshotNotFoundException::class);

        $this->fetcher()->fetchFromWayback('https://www.example.com/x');
    }

    #[Test]
    public function it_resolves_the_newest_snapshot_through_scrapfly_and_returns_its_html()
    {
        $this->fakeTwoStep(
            listing: $this->scrapflyResponse($this->listingHtml()),
            snapshot: $this->scrapflyResponse('<html>snapshot body</html>'),
        );

        $body = $this->fetcher()->fetchFromArchive('https://www.nytimes.com/some-article');

        $this->assertEquals('<html>snapshot body</html>', $body);

        // Step 1: the listing is scraped via ASP, JS render OFF, at {base}/{url}.
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['asp'] ?? null) === 'true'
                && ($query['render_js'] ?? null) === 'false'
                && ($query['url'] ?? null) === 'https://archive.ph/https://www.nytimes.com/some-article';
        });

        // Step 2: the NEWEST snapshot (CLBwm @ 18:47 in the fixture) is scraped
        // with JS render ON.
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['render_js'] ?? null) === 'true'
                && ($query['url'] ?? null) === 'https://archive.is/CLBwm';
        });
    }

    #[Test]
    public function it_picks_the_newest_among_multiple_dated_snapshots()
    {
        $listing = <<<'HTML'
        <html><body>
          <a href="https://archive.is/aaaaa"><div>10 Jan 2026 09:00</div></a>
          <a href="https://archive.is/zzzzz"><div>15 Mar 2026 12:00</div></a>
          <a href="https://archive.is/mmmmm"><div>02 Feb 2026 08:00</div></a>
        </body></html>
        HTML;

        $capturedSnapshotTarget = null;

        $this->fakeTwoStep(
            listing: $this->scrapflyResponse($listing),
            snapshot: function (Request $request) use (&$capturedSnapshotTarget) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $capturedSnapshotTarget = $query['url'] ?? null;

                return $this->scrapflyResponse('<html>newest</html>');
            },
        );

        $body = $this->fetcher()->fetchFromArchive('https://www.example.com/x');

        $this->assertEquals('<html>newest</html>', $body);
        // 15 Mar 2026 is the latest.
        $this->assertSame('https://archive.is/zzzzz', $capturedSnapshotTarget);
    }

    #[Test]
    public function it_throws_snapshot_not_found_when_the_listing_has_no_snapshots()
    {
        $this->fakeTwoStep(
            listing: $this->scrapflyResponse('<html><body>No results found.</body></html>'),
            snapshot: $this->scrapflyResponse('<html>unused</html>'),
        );

        $this->expectException(ArchiveSnapshotNotFoundException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/never-archived');
    }

    #[Test]
    public function it_throws_blocked_when_scrapfly_fails_on_the_listing()
    {
        $this->fakeTwoStep(
            listing: $this->scrapflyResponse('', 200, success: false),
            snapshot: $this->scrapflyResponse('<html>unused</html>'),
        );

        $this->expectException(ArchiveBlockedException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/x');
    }

    #[Test]
    public function it_throws_blocked_when_archive_answers_with_a_blocking_status()
    {
        $this->fakeTwoStep(
            listing: $this->scrapflyResponse('<html>blocked</html>', statusCode: 429),
            snapshot: $this->scrapflyResponse('<html>unused</html>'),
        );

        $this->expectException(ArchiveBlockedException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/x');
    }
}
