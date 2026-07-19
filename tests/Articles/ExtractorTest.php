<?php

namespace Tests\Articles;

use App\Articles\Article;
use App\Articles\Extractor;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExtractorTest extends TestCase
{
    private function extract(string $fixture, string $url): Article
    {
        $html = (string) file_get_contents(__DIR__."/fixtures/$fixture.html");

        return (new Extractor)->extract($url, $html);
    }

    #[Test]
    public function it_takes_the_body_from_json_ld_when_it_ships_the_article_body()
    {
        $article = $this->extract('jsonld-full-body', 'https://riversidegazette.com/city-council-approves-riverside-park');

        $this->assertEquals('City Council Approves Riverside Park', $article->title);
        $this->assertEquals('The Riverside Gazette', $article->publisher);
        $this->assertEquals(['Ada Reporter', 'Ben Writer'], $article->authors);
        $this->assertEquals(CarbonImmutable::parse('2021-06-15T09:30:00+00:00'), $article->publicationDate);
        $this->assertStringContainsString('ending a decade of debate', $article->text);
        $this->assertStringNotContainsString('Boilerplate', $article->text);
    }

    #[Test]
    public function it_takes_metadata_from_json_ld_and_the_body_from_readability()
    {
        $article = $this->extract('jsonld-metadata', 'https://metroherald.com/the-long-road-to-the-new-bridge');

        $this->assertEquals('The Long Road To The New Bridge', $article->title);
        $this->assertEquals('The Metropolitan Herald', $article->publisher);
        $this->assertEquals(['Clara Byline'], $article->authors);
        $this->assertEquals(CarbonImmutable::parse('2019-11-02T14:00:00+00:00'), $article->publicationDate);
        $this->assertStringContainsString('Engineers spent the better part of the morning', $article->text);
    }

    #[Test]
    public function it_takes_metadata_from_open_graph_tags_and_the_body_from_readability()
    {
        $article = $this->extract('og-tags', 'https://mountaindispatch.com/a-quiet-town-wakes-to-snow');

        $this->assertEquals('A Quiet Town Wakes To Snow', $article->title);
        $this->assertEquals('Mountain Dispatch', $article->publisher);
        $this->assertEquals(['Dana Columnist'], $article->authors);
        $this->assertEquals(CarbonImmutable::parse('2022-01-20T06:15:00+00:00'), $article->publicationDate);
        $this->assertStringContainsString('The first heavy snow of the season', $article->text);
    }

    #[Test]
    public function it_falls_back_to_the_domain_for_the_publisher_and_the_slug_for_the_title()
    {
        $article = $this->extract('bare', 'https://www.nytimes.com/breaking-news-story');

        $this->assertEquals('nytimes.com', $article->publisher);
        $this->assertEquals('Breaking News Story', $article->title);
        $this->assertStringContainsString('The harbor master reported calm seas', $article->text);
    }

    #[Test]
    public function it_derives_author_names_from_profile_url_slugs()
    {
        $article = $this->extract('authors-from-slugs', 'https://news.com/breaking-news');

        $this->assertEquals(['John Doe', 'Jane Doe'], $article->authors);
    }

    #[Test]
    public function it_prefers_og_title_when_the_page_title_reflects_it_but_not_the_json_ld_headline()
    {
        // Wikipedia fills schema.org headline with a short description; its own
        // <title> ("Podcast - Wikipedia") reflects og:title, not the headline.
        $article = $this->extract('wikipedia-headline', 'https://en.wikipedia.org/wiki/Podcast');

        $this->assertEquals('Podcast', $article->title);
    }

    #[Test]
    public function it_strips_a_trailing_site_name_from_the_page_title()
    {
        $article = $this->extract('title-site-suffix', 'https://dailybugle.example/library-vote');

        $this->assertEquals('Council Votes To Fund The New Library', $article->title);
    }

    #[Test]
    public function it_strips_a_leading_site_name_from_the_page_title()
    {
        $article = $this->extract('title-site-prefix', 'https://dailybugle.example/library-vote');

        $this->assertEquals('Council Votes To Fund The New Library', $article->title);
    }

    #[Test]
    public function it_strips_only_the_trailing_site_name_when_the_title_equals_the_site_name()
    {
        // The Wikipedia article about Wikipedia: "Wikipedia - Wikipedia" must
        // become "Wikipedia", not empty — only the trailing occurrence is a suffix.
        $article = $this->extract('title-name-equals-topic', 'https://en.wikipedia.org/wiki/Wikipedia');

        $this->assertEquals('Wikipedia', $article->title);
    }

    #[Test]
    public function it_defaults_the_publication_date_to_now_when_none_is_published()
    {
        $before = CarbonImmutable::now()->subMinute();

        $article = $this->extract('no-date', 'https://example.com/the-undated-almanac');

        // The rest of the metadata still extracts from the JSON-LD...
        $this->assertEquals('The Almanac That Forgot Its Own Date', $article->title);
        $this->assertEquals('The Timeless Register', $article->publisher);
        $this->assertEquals(['Morgan Undated'], $article->authors);

        // ...but with no date in the JSON-LD or the meta tags, it falls back to
        // "now" rather than failing the whole extraction.
        $this->assertTrue($article->publicationDate->greaterThanOrEqualTo($before));
        $this->assertTrue($article->publicationDate->lessThanOrEqualTo(CarbonImmutable::now()->addMinute()));
    }
}
