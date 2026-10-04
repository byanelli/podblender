<?php

namespace Tests\Articles;

use App\Articles\ArchiveSnapshotNotFoundException;
use App\Articles\Contracts\Reader;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReaderTest extends TestCase
{
    private function cleanHtml(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/clean-full.html');
    }

    private function gatedHtml(): string
    {
        return '<html><head><script type="application/ld+json">'
            .'{"@type":"NewsArticle","headline":"Gated Direct Page","isAccessibleForFree":false}'
            .'</script></head><body><p>Members only.</p></body></html>';
    }

    /**
     * A Scrapfly scrape JSON envelope wrapping the given target HTML.
     */
    private function scrapfly(string $content, int $statusCode = 200): PromiseInterface
    {
        return Http::response([
            'result' => [
                'content'     => $content,
                'url'         => 'https://archive.is/final',
                'status_code' => $statusCode,
                'success'     => true,
            ],
        ]);
    }

    /**
     * Fake the direct fetch and the archive request. Any non-Scrapfly host
     * returns $direct, and the Scrapfly request returns $snapshot.
     */
    private function fake(string $direct, string $snapshot): void
    {
        Http::fake(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io')
            ? $this->scrapfly($snapshot)
            : Http::response($direct));
    }

    /**
     * Fake all three tiers. A null $wayback means the availability API reports
     * no snapshot; a string is the snapshot HTML from web.archive.org. Scrapfly
     * requests behave as in fake().
     */
    private function fakeCascade(string $direct, ?string $wayback, string $archiveSnapshot): void
    {
        Http::fake(function (Request $request) use ($direct, $wayback, $archiveSnapshot) {
            $url = $request->url();

            if (str_starts_with($url, 'https://archive.org/wayback/available')) {
                return $wayback === null
                    ? Http::response(['archived_snapshots' => []])
                    : Http::response(['archived_snapshots' => ['closest' => [
                        'available' => true,
                        'url'       => 'http://web.archive.org/web/20260115184700/x',
                        'timestamp' => '20260115184700',
                        'status'    => '200',
                    ]]]);
            }

            if (str_starts_with($url, 'https://web.archive.org/web/')) {
                return Http::response((string) $wayback);
            }

            if (str_starts_with($url, 'https://api.scrapfly.io')) {
                return $this->scrapfly($archiveSnapshot);
            }

            return Http::response($direct);
        });
    }

    private function assertWaybackSnapshotFetched(): void
    {
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://web.archive.org/web/'));
    }

    private function assertScrapflySent(): void
    {
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'));
    }

    private function assertScrapflyNotSent(): void
    {
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'));
    }

    private function reader(): Reader
    {
        return $this->app->make(Reader::class);
    }

    #[Test]
    public function it_returns_the_direct_article_and_never_touches_the_archive_for_a_clean_page()
    {
        Http::fake(['*' => Http::response($this->cleanHtml())]);

        $article = $this->reader()->read('https://theopenpress.com/harvest-festival');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // Neither Wayback nor the paid Scrapfly/archive.is tier is requested.
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://web.archive.org/')
            || str_starts_with($request->url(), 'https://archive.org/wayback/')
            || str_starts_with($request->url(), 'https://api.scrapfly.io'));
    }

    #[Test]
    public function it_uses_the_free_wayback_tier_when_direct_is_gated_and_never_spends_scrapfly_credits()
    {
        // The direct page is paywalled and Wayback has the full article.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: $this->cleanHtml(),
            archiveSnapshot: '<html>unused archive</html>',
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // Scrapfly requests cost credits, so none is sent once Wayback succeeds.
        $this->assertWaybackSnapshotFetched();
        $this->assertScrapflyNotSent();
    }

    #[Test]
    public function it_falls_through_wayback_to_the_archive_when_the_wayback_snapshot_is_also_gated()
    {
        // Wayback's crawler was served the paywall too, so its snapshot is paywalled.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: $this->gatedHtml(),
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        // This title comes from the archive.is snapshot.
        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        $this->assertWaybackSnapshotFetched();
        $this->assertScrapflySent();
    }

    #[Test]
    public function it_falls_through_to_the_archive_when_wayback_has_no_snapshot()
    {
        // Many paywalled outlets block the Wayback crawler, so no snapshot exists.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: null,
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // The availability API was queried, but no Wayback snapshot was fetched.
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://archive.org/wayback/available'));
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://web.archive.org/web/'));
        $this->assertScrapflySent();
    }

    #[Test]
    public function it_skips_the_direct_fetch_for_a_hard_paywall_domain_and_cascades_wayback_then_archive()
    {
        // NYT blocks the Wayback crawler, so only archive.is has the article.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: null,
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://www.nytimes.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://nytimes.com/some-article');

        // Wayback is still tried before archive.is, with the URL that keeps "www.".
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://archive.org/wayback/available')
                && ($query['url'] ?? null) === 'https://www.nytimes.com/some-article';
        });

        $this->assertScrapflySent();
    }

    #[Test]
    public function it_goes_straight_to_the_archive_for_a_hard_paywall_domain()
    {
        $this->fake(direct: $this->gatedHtml(), snapshot: $this->cleanHtml());

        $article = $this->reader()->read('https://www.nytimes.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://nytimes.com/some-article');

        // archive.is indexes the New York Times under www.nytimes.com, so the
        // lookup keeps "www.".
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.scrapfly.io')
                && str_contains((string) ($query['url'] ?? ''), 'www.nytimes.com');
        });
    }

    /**
     * Fake a hard-paywall read in which Wayback has no snapshot and archive.is
     * has one only for the www. form of the URL.
     */
    private function fakeArchiveWithOnlyWwwSnapshot(): void
    {
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://archive.org/wayback/available')) {
                return Http::response(['archived_snapshots' => []]);
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains((string) ($query['url'] ?? ''), 'www.nytimes.com')
                ? $this->scrapfly($this->cleanHtml())
                : $this->scrapfly('<html>No results</html>', statusCode: 404);
        });
    }

    private function scrapflyRequestCount(): int
    {
        return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'))->count();
    }

    #[Test]
    public function it_retries_the_archive_lookup_with_www_when_the_bare_host_has_no_snapshot()
    {
        $this->fakeArchiveWithOnlyWwwSnapshot();

        $article = $this->reader()->read('https://nytimes.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);
        $this->assertEquals(2, $this->scrapflyRequestCount());
    }

    #[Test]
    public function it_does_not_retry_the_archive_lookup_when_the_url_already_has_www()
    {
        Http::fake(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io')
            ? $this->scrapfly('<html>No results</html>', statusCode: 404)
            : Http::response(['archived_snapshots' => []]));

        try {
            $this->reader()->read('https://www.nytimes.com/some-article');
            $this->fail('Expected ArchiveSnapshotNotFoundException.');
        } catch (ArchiveSnapshotNotFoundException) {
            $this->assertEquals(1, $this->scrapflyRequestCount());
        }
    }

    /**
     * Fake a read in which the scraper returns $giftPage for a URL with a gift
     * parameter and $archiveSnapshot for anything else. Wayback has no snapshot.
     */
    private function fakeGiftLink(string $giftPage, string $archiveSnapshot): void
    {
        Http::fake(function (Request $request) use ($giftPage, $archiveSnapshot) {
            if (str_starts_with($request->url(), 'https://archive.org/wayback/available')) {
                return Http::response(['archived_snapshots' => []]);
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains((string) ($query['url'] ?? ''), 'gift=')
                ? $this->scrapfly($giftPage)
                : $this->scrapfly($archiveSnapshot);
        });
    }

    /**
     * @return list<string>
     */
    private function scrapedUrls(): array
    {
        return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'))
            ->map(function (array $pair) {
                $query = [];
                parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

                return (string) ($query['url'] ?? '');
            })
            ->values()
            ->all();
    }

    #[Test]
    public function it_reads_a_gift_link_through_the_scraper_before_the_archives()
    {
        $this->fakeGiftLink(giftPage: $this->cleanHtml(), archiveSnapshot: $this->gatedHtml());

        $article = $this->reader()->read('https://www.theatlantic.com/a/1/?gift=abc');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);
        $this->assertSame(['https://www.theatlantic.com/a/1/?gift=abc'], $this->scrapedUrls());
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://archive.org/'));
    }

    #[Test]
    public function it_falls_back_to_the_archives_without_the_gift_parameter_when_the_gift_link_is_paywalled()
    {
        $this->fakeGiftLink(giftPage: $this->gatedHtml(), archiveSnapshot: $this->cleanHtml());

        $article = $this->reader()->read('https://www.theatlantic.com/a/1/?gift=abc');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);
        $this->assertSame([
            'https://www.theatlantic.com/a/1/?gift=abc',
            'https://archive.ph/newest/https://www.theatlantic.com/a/1/',
        ], $this->scrapedUrls());
    }

    #[Test]
    public function it_serves_the_url_without_the_gift_parameter_from_the_gift_links_cache_entry()
    {
        $this->fakeGiftLink(giftPage: $this->cleanHtml(), archiveSnapshot: $this->gatedHtml());

        $this->reader()->read('https://www.theatlantic.com/a/1/?gift=abc');

        // A clip's download reads its canonical URL, which has no "www." or
        // gift parameter.
        $article = $this->reader()->read('https://theatlantic.com/a/1/');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);
        $this->assertCount(1, $this->scrapedUrls());
    }

    #[Test]
    public function it_serves_a_second_read_of_the_same_url_from_cache()
    {
        Http::fake(['*' => Http::response($this->cleanHtml())]);

        $first = $this->reader()->read('https://theopenpress.com/harvest-festival');
        $second = $this->reader()->read('https://theopenpress.com/harvest-festival');

        $this->assertEquals($first->title, $second->title);

        Http::assertSentCount(1);
    }
}
