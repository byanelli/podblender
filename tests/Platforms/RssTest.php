<?php

namespace Tests\Platforms;

use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Exceptions\FeedNotFoundException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Rss;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RssTest extends TestCase
{
    private function rss(): Rss
    {
        return $this->app->make(Rss::class);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__."/fixtures/$name");
    }

    #[Test]
    public function it_gets_source_metadata_straight_from_a_feed_url()
    {
        Http::fake(['https://riversidegazette.com/feed.xml' => Http::response($this->fixture('feed-rss2.xml'))]);

        $metadata = $this->rss()->getSourceMetadata('https://riversidegazette.com/feed.xml');

        $this->assertEquals('The Riverside Gazette', $metadata->name);
        $this->assertEquals('https://riversidegazette.com/feed.xml', $metadata->canonicalUrl);
    }

    #[Test]
    public function it_discovers_the_feed_advertised_by_an_html_page()
    {
        Http::fake([
            'https://riversidegazette.com' => Http::response($this->fixture('page-with-feed-link.html')),
            'https://riversidegazette.com/feed.xml' => Http::response($this->fixture('feed-rss2.xml')),
        ]);

        $metadata = $this->rss()->getSourceMetadata('https://riversidegazette.com');

        $this->assertEquals('The Riverside Gazette', $metadata->name);
        // The source's canonical URL is the FEED the page advertised (with the
        // relative href resolved) — that's what UpdateSubscription will poll.
        $this->assertEquals('https://riversidegazette.com/feed.xml', $metadata->canonicalUrl);
    }

    #[Test]
    public function it_throws_when_a_page_advertises_no_feed()
    {
        Http::fake(['*' => Http::response('<html><head><title>No feeds here</title></head></html>')]);

        try {
            $this->rss()->getSourceMetadata('https://example.com');
            $this->fail('Expected a PlatformException');
        } catch (PlatformException $e) {
            $this->assertInstanceOf(FeedNotFoundException::class, $e->getPrevious());
            $this->assertStringContainsString('Rss', $e->getMessage());
        }
    }

    #[Test]
    public function it_lists_feed_items_published_since_a_cutoff_without_reading_their_pages()
    {
        Http::fake(['https://riversidegazette.com/feed.xml' => Http::response($this->fixture('feed-rss2.xml'))]);

        $clips = $this->rss()->getMetadataForAllClipsPublishedSince(
            'https://riversidegazette.com/feed.xml',
            CarbonImmutable::parse('2026-07-01T00:00:00+00:00'),
        );

        // The June item is filtered out by the feed's own dates.
        $this->assertCount(1, $clips);

        $clip = $clips[0];
        $this->assertInstanceOf(ClipMetadata::class, $clip);
        $this->assertEquals('City Council Approves Riverside Park', $clip->title);
        // The item's HTML description is flattened to plain text.
        $this->assertEquals('The council voted unanimously on Tuesday.', $clip->description);
        // The item link is canonicalized the same way a hand-pasted article
        // URL would be (https, no www, no utm_*), so the two paths dedupe.
        $this->assertEquals('https://riversidegazette.com/city-council-approves-riverside-park', $clip->canonicalUrl);
        $this->assertEquals(CarbonImmutable::parse('2026-07-15T09:30:00+00:00'), $clip->publishedAt);
        $this->assertEquals('The Riverside Gazette', $clip->source->name);
        $this->assertEquals('https://riversidegazette.com/feed.xml', $clip->source->canonicalUrl);

        // A fully-dated, fully-titled feed costs exactly one request: the feed
        // itself. No article page is ever fetched.
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_parses_an_atom_feed()
    {
        Http::fake(['https://mountaindispatch.com/atom.xml' => Http::response($this->fixture('feed-atom.xml'))]);

        $clips = $this->rss()->getMetadataForAllClipsPublishedSince(
            'https://mountaindispatch.com/atom.xml',
            CarbonImmutable::parse('2026-07-01T00:00:00+00:00'),
        );

        $this->assertCount(1, $clips);
        $this->assertEquals('A Quiet Town Wakes To Snow', $clips[0]->title);
        $this->assertEquals('Snow arrived overnight.', $clips[0]->description);
        $this->assertEquals('https://mountaindispatch.com/a-quiet-town-wakes-to-snow', $clips[0]->canonicalUrl);
        $this->assertEquals(CarbonImmutable::parse('2026-07-10T06:15:00+00:00'), $clips[0]->publishedAt);
        $this->assertEquals('Mountain Dispatch', $clips[0]->source->name);
    }

    #[Test]
    public function it_fills_in_an_undated_item_by_reading_the_article_with_the_feed_as_hints()
    {
        Http::fake(function (Request $request) {
            return match (true) {
                str_contains($request->url(), 'feed.xml') => Http::response($this->fixture('feed-sparse.xml')),
                default => Http::response((string) file_get_contents(__DIR__.'/../Articles/fixtures/clean-full.html')),
            };
        });

        $clips = $this->rss()->getMetadataForAllClipsPublishedSince(
            'https://theopenpress.com/feed.xml',
            CarbonImmutable::parse('2020-01-01T00:00:00+00:00'),
        );

        $this->assertCount(1, $clips);

        // The feed's title outranks the page's own metadata (the page says
        // "A Complete, Freely Readable Article")...
        $this->assertEquals('Feed Title That Wins', $clips[0]->title);
        // ...while the date the feed omitted comes from the page.
        $this->assertEquals(CarbonImmutable::parse('2023-03-03T12:00:00+00:00'), $clips[0]->publishedAt);
        // The item had no description either, so the article's authors stand in.
        $this->assertEquals('Article by Freely Available', $clips[0]->description);

        // The article page was fetched (feed + article = two requests).
        Http::assertSentCount(2);
    }
}
