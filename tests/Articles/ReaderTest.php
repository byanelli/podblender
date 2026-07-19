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

        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'));
    }

    #[Test]
    public function it_falls_back_to_the_archive_when_the_direct_page_is_gated()
    {
        $this->fake(direct: $this->gatedHtml(), snapshot: $this->cleanHtml());

        $article = $this->reader()->read('https://theonion.com/some-article');

        // The clean archive article, not the gated direct one.
        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.scrapfly.io'));
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
