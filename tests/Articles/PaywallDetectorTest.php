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
    /** 527 chars, which is over the 500-char min_body_length. */
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
    public function it_ignores_the_flag_when_the_json_ld_contains_the_body()
    {
        // Metered sites such as CNN and The Verge set the flag on pages that
        // contain the whole article.
        $html = $this->jsonLdPage(json_encode([
            '@type'               => 'NewsArticle',
            'isAccessibleForFree' => false,
            'articleBody'         => self::LONG_BODY,
        ]));

        $this->assertFalse($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_ignores_the_flag_when_the_body_meets_the_declared_word_count()
    {
        $html = $this->jsonLdPage('{"@type":"NewsArticle","isAccessibleForFree":false,"wordCount":100}');

        $this->assertFalse($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_ignores_a_marker_phrase_inside_the_article()
    {
        // From Wikipedia's article on the Enigma machine.
        $sentence = 'Polish cryptologists developed techniques and designed mechanical devices to continue reading Enigma traffic.';
        $html = "<html><body><p>{$sentence}</p></body></html>";

        $this->assertFalse($this->detector()->isGated($html, $this->article($sentence.' '.self::LONG_BODY)));
    }

    #[Test]
    public function it_counts_a_marker_phrase_at_the_end_of_the_article()
    {
        // Readability keeps Substack's prompt as the last paragraph of the body.
        $prompt = 'Keep reading with a 7-day free trial. Subscribe to Slow Boring to keep reading this post.';
        $html = "<html><body><p>The first paragraphs.</p><p>{$prompt}</p></body></html>";

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY.' '.$prompt)));
    }

    #[Test]
    public function it_ignores_marker_phrases_in_scripts_and_styles()
    {
        $html = '<html><head><style>.cta:after{content:"Subscribe to continue"}</style>'
            .'<script>var copy = {"gate":"Subscribe to read"};</script></head><body><p>Body.</p></body></html>';

        $this->assertFalse($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_ignores_generic_paywall_markup_and_sign_in_links()
    {
        // Most news sites have both on every page, including full articles.
        $html = '<html><head><style>.paywall-bar{display:none}</style></head>'
            .'<body><a href="/login">Already a subscriber? Sign in</a><p>Body.</p></body></html>';

        $this->assertFalse($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_treats_a_substack_paid_post_as_gated()
    {
        $html = '<html><body><p>The first paragraphs.</p><h2>This post is for paid subscribers</h2>'
            .'<a>Subscribe</a><a>Already a paid subscriber? Sign in</a></body></html>';

        $this->assertTrue($this->detector()->isGated($html, $this->article(self::LONG_BODY)));
    }

    #[Test]
    public function it_does_not_gate_a_clean_full_article()
    {
        $html = (string) file_get_contents(__DIR__.'/fixtures/clean-full.html');

        $article = $this->app->make(Extractor::class)->extract('https://theopenpress.com/harvest-festival', $html);

        $this->assertFalse($this->detector()->isGated($html, $article));
    }
}
