<?php

namespace Tests\Articles;

use App\Articles\ArchiveBlockedException;
use App\Articles\ArchiveSnapshotNotFoundException;
use App\Articles\Contracts\Fetcher;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetcherTest extends TestCase
{
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
                'content' => $content,
                'url' => 'https://archive.is/final',
                'status_code' => $statusCode,
                'success' => $success,
                'cost' => ['total' => 30, 'details' => []],
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
