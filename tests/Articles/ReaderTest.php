<?php

namespace Tests\Articles;

use App\Articles\Contracts\Reader;
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

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'archive.ph'));
    }

    #[Test]
    public function it_falls_back_to_the_archive_when_the_direct_page_is_gated()
    {
        Http::fake([
            'https://archive.ph/*' => Http::response($this->cleanHtml()),
            '*' => Http::response($this->gatedHtml()),
        ]);

        $article = $this->reader()->read('https://theonion.com/some-article');

        // The clean archive article, not the gated direct one.
        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'archive.ph/newest/'));
    }

    #[Test]
    public function it_goes_straight_to_the_archive_for_a_hard_paywall_domain()
    {
        Http::fake([
            'https://archive.ph/*' => Http::response($this->cleanHtml()),
            '*' => Http::response($this->gatedHtml()),
        ]);

        $article = $this->reader()->read('https://www.nytimes.com/some-article');

        $this->assertEquals('A Complete, Freely Readable Article', $article->title);

        // The doomed direct fetch is skipped entirely.
        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://nytimes.com/some-article');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'archive.ph/newest/'));
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
