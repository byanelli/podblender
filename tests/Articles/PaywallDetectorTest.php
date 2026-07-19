<?php

namespace Tests\Articles;

use App\Articles\Article;
use App\Articles\Extractor;
use App\Articles\PaywallDetector;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaywallDetectorTest extends TestCase
{
    /** A body comfortably over the 500-char min_body_length, ~120 words. */
    private const LONG_BODY = 'The council met for three hours on Tuesday evening to work through a crowded agenda that ranged from the routine approval of last month minutes to a contentious debate over the proposed rezoning of the old cannery district near the waterfront. Residents packed the chamber to voice their concerns, and several stayed well past midnight to make sure their comments were entered into the official record before the final vote was called by the presiding chair of the assembled body of local representatives who governed the town.';

    private function detector(): PaywallDetector
    {
        return $this->app->make(PaywallDetector::class);
    }

    private function article(string $text): Article
    {
        return new Article(
            url: 'https://news.com/a',
            title: 'A',
            publisher: 'news.com',
            publicationDate: CarbonImmutable::now(),
            authors: [],
            text: $text,
        );
    }

    private function jsonLdPage(string $json): string
    {
        return '<html><head><script type="application/ld+json">'.$json.'</script></head><body></body></html>';
    }

    #[Test]
    public function it_treats_is_accessible_for_free_false_as_gated()
    {
        $html = $this->jsonLdPage('{"@type":"NewsArticle","isAccessibleForFree":false}');

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_treats_a_nested_has_part_gate_as_gated()
    {
        $html = $this->jsonLdPage('{"@type":"NewsArticle","isAccessibleForFree":true,"hasPart":[{"@type":"WebPageElement","isAccessibleForFree":false}]}');

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_treats_a_word_count_far_above_the_extracted_body_as_gated()
    {
        $html = $this->jsonLdPage('{"@type":"NewsArticle","wordCount":2000}');

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_treats_a_paywall_marker_string_as_gated()
    {
        $html = '<html><body><p>Subscribe to continue reading this article.</p></body></html>';

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_treats_a_body_under_the_minimum_length_as_gated()
    {
        $html = '<html><body><p>Too short to be a real article.</p></body></html>';

        $this->assertTrue($this->detector()->isGated($html, $this->article('Too short to be a real article.')));
    }

    #[Test]
    public function it_does_not_gate_a_clean_full_article()
    {
        $html = (string) file_get_contents(__DIR__.'/fixtures/clean-full.html');

        $article = (new Extractor)->extract('https://theopenpress.com/harvest-festival', $html);

        $this->assertFalse($this->detector()->isGated($html, $article));
    }
}
