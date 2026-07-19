<?php

namespace Tests\Articles;

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

    private function listingHtml(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/archive-today-listing.html');
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
    private function scrapfly(string $content): PromiseInterface
    {
        return Http::response([
            'result' => [
                'content' => $content,
                'url' => 'https://archive.is/final',
                'status_code' => 200,
                'success' => true,
            ],
        ]);
    }

    /**
     * Fake both hops: a non-Scrapfly host gets $direct; Scrapfly's listing hop
     * gets the fixture listing, and its snapshot hop gets $snapshot.
     */
    private function fake(string $direct, string $snapshot): void
    {
        Http::fake(function (Request $request) use ($direct, $snapshot) {
            if (! str_starts_with($request->url(), 'https://api.scrapfly.io')) {
                return Http::response($direct);
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $target = (string) ($query['url'] ?? '');

            return preg_match('~^https?://archive\.[a-z]+/[A-Za-z0-9]{5}$~', $target)
                ? $this->scrapfly($snapshot)
                : $this->scrapfly($this->listingHtml());
        });
    }

    /**
     * Fake all three tiers at once. $wayback = null means the availability API
     * reports NO snapshot; a string is the raw snapshot HTML web.archive.org
     * serves. Scrapfly's listing/snapshot hops behave as in fake().
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
                        'url' => 'http://web.archive.org/web/20260115184700/x',
                        'timestamp' => '20260115184700',
                        'status' => '200',
                    ]]]);
            }

            if (str_starts_with($url, 'https://web.archive.org/web/')) {
                return Http::response((string) $wayback);
            }

            if (str_starts_with($url, 'https://api.scrapfly.io')) {
                $query = [];
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $target = (string) ($query['url'] ?? '');

                return preg_match('~^https?://archive\.[a-z]+/[A-Za-z0-9]{5}$~', $target)
                    ? $this->scrapfly($archiveSnapshot)
                    : $this->scrapfly($this->listingHtml());
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

        // A clean direct page settles on the free tier: neither the free Wayback
        // tier nor the paid Scrapfly/archive.is tier is touched.
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://web.archive.org/')
            || str_starts_with($request->url(), 'https://archive.org/wayback/')
            || str_starts_with($request->url(), 'https://api.scrapfly.io'));
    }

    #[Test]
    public function it_uses_the_free_wayback_tier_when_direct_is_gated_and_never_spends_scrapfly_credits()
    {
        // Direct is gated, but Wayback has the full, un-paywalled capture.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: $this->cleanHtml(),
            archiveSnapshot: '<html>unused archive</html>',
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // The credit-saving path: Wayback answered, so archive.is/Scrapfly is
        // never called.
        $this->assertWaybackSnapshotFetched();
        $this->assertScrapflyNotSent();
    }

    #[Test]
    public function it_falls_through_wayback_to_the_archive_when_the_wayback_snapshot_is_also_gated()
    {
        // Wayback's crawler hit the same paywall: its snapshot is a teaser too.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: $this->gatedHtml(),
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        // The clean archive.is article, not the gated Wayback teaser.
        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        $this->assertWaybackSnapshotFetched();
        $this->assertScrapflySent();
    }

    #[Test]
    public function it_falls_through_to_the_archive_when_wayback_has_no_snapshot()
    {
        // Many gated outlets block the Wayback crawler, so there is no capture.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: null,
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://theonion.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // Availability was consulted, no snapshot was fetched, archive.is closed it.
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://archive.org/wayback/available'));
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://web.archive.org/web/'));
        $this->assertScrapflySent();
    }

    #[Test]
    public function it_skips_the_direct_fetch_for_a_hard_paywall_domain_and_cascades_wayback_then_archive()
    {
        // NYT blocks the Wayback crawler, so the free tiers miss and archive.is
        // closes it — all without ever attempting the doomed direct fetch.
        $this->fakeCascade(
            direct: $this->gatedHtml(),
            wayback: null,
            archiveSnapshot: $this->cleanHtml(),
        );

        $article = $this->reader()->read('https://www.nytimes.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // No direct fetch for the hard-paywall host.
        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://nytimes.com/some-article');

        // Wayback (free) is still tried first — on the www-preserved canonical URL.
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

        // The doomed direct fetch is skipped entirely.
        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://nytimes.com/some-article');

        // The archive lookup uses the www-PRESERVED canonical URL, because that
        // is how archive.is indexes the New York Times.
        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.scrapfly.io')
                && str_contains((string) ($query['url'] ?? ''), 'www.nytimes.com');
        });
    }

    #[Test]
    public function it_serves_a_second_read_of_the_same_url_from_cache()
    {
        Http::fake(['*' => Http::response($this->cleanHtml())]);

        $first = $this->reader()->read('https://theopenpress.com/harvest-festival');
        $second = $this->reader()->read('https://theopenpress.com/harvest-festival');

        $this->assertEquals($first->title, $second->title);

        // One fetch total: the second read comes from cache.
        Http::assertSentCount(1);
    }
}
