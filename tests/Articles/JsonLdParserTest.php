<?php

namespace Tests\Articles;

use App\Articles\JsonLd;
use App\Articles\JsonLdParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonLdParserTest extends TestCase
{
    /**
     * @param  array<mixed>  ...$blocks
     */
    private function parse(array ...$blocks): JsonLd
    {
        $html = collect($blocks)
            ->map(fn (array $block) => '<script type="application/ld+json">'.json_encode($block).'</script>')
            ->implode('');

        return (new JsonLdParser)->parse("<html><head>{$html}</head></html>");
    }

    #[Test]
    public function it_reads_the_article_node_from_a_graph()
    {
        $jsonLd = $this->parse(['@graph' => [
            ['@type' => 'WebSite', 'headline' => 'Not this'],
            [
                '@type'         => ['NewsArticle'],
                'headline'      => '  A Headline  ',
                'articleBody'   => 'The body.',
                'publisher'     => ['@type' => 'Organization', 'name' => 'The Paper'],
                'datePublished' => '2026-03-15T09:00:00Z',
                'wordCount'     => '1200',
            ],
        ]]);

        $this->assertSame('A Headline', $jsonLd->headline);
        $this->assertSame('The body.', $jsonLd->articleBody);
        $this->assertSame('The Paper', $jsonLd->publisherName);
        $this->assertEquals(CarbonImmutable::parse('2026-03-15T09:00:00Z'), $jsonLd->datePublished);
        $this->assertSame(1200, $jsonLd->wordCount);
    }

    #[Test]
    public function it_finds_the_article_node_in_a_later_block_or_a_list()
    {
        $jsonLd = $this->parse(
            ['@type' => 'BreadcrumbList'],
            [['@type' => 'Person'], ['@type' => 'BlogPosting', 'headline' => 'Found']],
        );

        $this->assertSame('Found', $jsonLd->headline);
    }

    #[Test]
    public function it_returns_empty_fields_without_an_article_node()
    {
        $this->assertEquals(new JsonLd, $this->parse(['@type' => 'WebPage', 'headline' => 'A page']));
        $this->assertEquals(new JsonLd, (new JsonLdParser)->parse('<script type="application/ld+json">{not json</script>'));
    }

    #[Test]
    public function it_reads_authors_in_every_form_the_schema_allows()
    {
        $single = $this->parse(['@type' => 'Article', 'author' => ['@type' => 'Person', 'name' => 'Ada Lovelace']]);
        $mixed = $this->parse(['@type' => 'Article', 'author' => [
            'Grace Hopper',
            ['name' => 'Alan Turing'],
            ['url'  => 'https://example.com/authors/edsger-dijkstra'],
            '  ',
            ['name' => ''],
        ]]);

        $this->assertSame(['Ada Lovelace'], $single->authors);
        $this->assertSame(
            ['Grace Hopper', 'Alan Turing', 'https://example.com/authors/edsger-dijkstra'],
            $mixed->authors
        );
    }

    #[Test]
    public function it_treats_blank_strings_and_bad_dates_as_missing()
    {
        $jsonLd = $this->parse([
            '@type'         => 'Article',
            'headline'      => '   ',
            'articleBody'   => '',
            'datePublished' => 'not a date',
            'wordCount'     => 'many',
        ]);

        $this->assertNull($jsonLd->headline);
        $this->assertNull($jsonLd->articleBody);
        $this->assertNull($jsonLd->datePublished);
        $this->assertNull($jsonLd->wordCount);
    }

    #[Test]
    public function it_resolves_id_references_for_authors_and_the_publisher()
    {
        // WordPress and Yoast link nodes by @id, sometimes across blocks.
        $jsonLd = $this->parse(
            ['@graph' => [
                [
                    '@type'     => 'Article',
                    'author'    => [['@id' => 'https://example.com/#/person/1'], 'Grace Hopper'],
                    'publisher' => ['@id' => 'https://example.com/#organization'],
                ],
                ['@type' => 'Person', '@id' => 'https://example.com/#/person/1', 'name' => 'Maddy Osman'],
            ]],
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'Example News'],
        );

        $this->assertSame(['Maddy Osman', 'Grace Hopper'], $jsonLd->authors);
        $this->assertSame('Example News', $jsonLd->publisherName);
    }

    #[Test]
    public function it_decodes_html_entities()
    {
        $jsonLd = $this->parse([
            '@type'    => 'NewsArticle',
            'headline' => 'Beshear says he didn&#8217;t write book',
            'author'   => ['name' => 'Tom &amp; Jerry'],
        ]);

        $this->assertSame('Beshear says he didn’t write book', $jsonLd->headline);
        $this->assertSame(['Tom & Jerry'], $jsonLd->authors);
    }

    #[Test]
    public function it_joins_a_live_blogs_updates_oldest_first()
    {
        $jsonLd = $this->parse(['@type' => 'LiveBlogPosting', 'headline' => 'Live: the summit', 'liveBlogUpdate' => [
            ['@type' => 'BlogPosting', 'headline' => 'Talks end', 'articleBody' => 'The leaders left.'],
            ['@type' => 'BlogPosting', 'headline' => 'Live: the summit', 'articleBody' => 'A delay.'],
            ['@type' => 'BlogPosting', 'headline' => 'Talks begin', 'articleBody' => 'The leaders arrived.'],
        ]]);

        // An update that repeats the blog's headline is read without it.
        $this->assertSame(
            "Talks begin. The leaders arrived.\n\nA delay.\n\nTalks end. The leaders left.",
            $jsonLd->articleBody
        );
    }

    #[Test]
    public function it_prefers_an_article_node_that_has_a_body()
    {
        // CNN's live pages have an empty NewsArticle before the LiveBlogPosting.
        $jsonLd = $this->parse([
            ['@type' => 'NewsArticle', 'headline' => 'Wrapper', 'articleBody' => ''],
            ['@type' => 'LiveBlogPosting', 'headline' => 'Live', 'liveBlogUpdate' => [
                ['articleBody' => 'An update.'],
            ]],
        ]);

        $this->assertSame('Live', $jsonLd->headline);
        $this->assertSame('An update.', $jsonLd->articleBody);
    }

    #[Test]
    public function it_reports_an_article_with_a_paywalled_section_as_not_free()
    {
        $jsonLd = $this->parse([
            '@type'               => 'NewsArticle',
            'isAccessibleForFree' => 'True',
            'hasPart'             => ['@type' => 'WebPageElement', 'isAccessibleForFree' => 'False'],
        ]);

        $this->assertFalse($jsonLd->isAccessibleForFree);
        $this->assertTrue($this->parse(['@type' => 'Article', 'isAccessibleForFree' => true])->isAccessibleForFree);
        $this->assertNull($this->parse(['@type' => 'Article'])->isAccessibleForFree);
    }
}
