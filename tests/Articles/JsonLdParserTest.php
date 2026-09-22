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
