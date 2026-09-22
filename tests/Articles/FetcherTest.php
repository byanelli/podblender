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

        // The real config puts a random session id in the proxy URL. A fixed
        // URL lets the Wayback tests assert the exact proxy used.
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
     * Fake the scraper's answer to the archive request, and capture the
     * request's target `url` and `render_js` query params.
     *
     * @param  array<string, string|null>  $captured
     */
    private function fakeArchive(PromiseInterface $response, array &$captured = []): void
    {
        Http::fake(function (Request $request) use ($response, &$captured) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $captured = ['url' => $query['url'] ?? null, 'render_js' => $query['render_js'] ?? null];

            return $response;
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

        // Both Wayback requests are proxied, because archive.org rate-limits and
        // blocks production's datacenter IP.
        $this->assertCount(2, $proxied);
        $this->assertSame([self::PROXY_URL, self::PROXY_URL], $proxied);

        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://archive.org/wayback/available')
                && ($query['url'] ?? null) === 'https://www.example.com/x';
        });

        // The "id_" modifier after the timestamp returns the page without the
        // Wayback toolbar.
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
    public function it_fetches_the_newest_archive_snapshot_in_one_rendered_request()
    {
        $captured = [];
        $this->fakeArchive($this->scrapflyResponse('<html>snapshot body</html>'), $captured);

        $body = $this->fetcher()->fetchFromArchive('https://www.nytimes.com/some-article');

        $this->assertEquals('<html>snapshot body</html>', $body);

        // archive.today redirects /newest/{url} to the newest snapshot.
        $this->assertSame('https://archive.ph/newest/https://www.nytimes.com/some-article', $captured['url']);
        $this->assertSame('true', $captured['render_js']);
    }

    #[Test]
    public function it_throws_snapshot_not_found_when_the_archive_answers_404()
    {
        $this->fakeArchive($this->scrapflyResponse('<html>No results</html>', statusCode: 404));

        $this->expectException(ArchiveSnapshotNotFoundException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/never-archived');
    }

    #[Test]
    public function it_throws_blocked_when_the_scraper_fails()
    {
        $this->fakeArchive($this->scrapflyResponse('', 200, success: false));

        $this->expectException(ArchiveBlockedException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/x');
    }

    #[Test]
    public function it_throws_blocked_when_the_archive_answers_with_another_error_status()
    {
        $this->fakeArchive($this->scrapflyResponse('<html>blocked</html>', statusCode: 429));

        $this->expectException(ArchiveBlockedException::class);

        $this->fetcher()->fetchFromArchive('https://www.example.com/x');
    }
}
