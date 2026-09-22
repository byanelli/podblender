<?php

namespace Tests\Articles;

use App\Articles\MetaTagParser;
use App\Articles\MetaTags;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MetaTagParserTest extends TestCase
{
    #[Test]
    public function it_reads_property_and_name_tags()
    {
        $meta = (new MetaTagParser)->parse(<<<'HTML'
            <html><head>
            <meta property="og:title" content="Tom &amp; Jerry">
            <meta name="twitter:title" content=" Tweet title ">
            <meta property="OG:SITE_NAME" content='The Paper'>
            <meta name="author" content="Ada Lovelace">
            <meta property="article:author" content="https://example.com/authors/ada">
            <meta property="article:published_time" content="2026-03-15T09:00:00Z">
            <meta property="og:published_time" content="2026-03-14">
            </head></html>
            HTML);

        $this->assertEquals(new MetaTags(
            ogTitle: 'Tom & Jerry',
            twitterTitle: 'Tweet title',
            ogSiteName: 'The Paper',
            author: 'Ada Lovelace',
            articleAuthor: 'https://example.com/authors/ada',
            articlePublishedTime: CarbonImmutable::parse('2026-03-15T09:00:00Z'),
            ogPublishedTime: CarbonImmutable::parse('2026-03-14'),
        ), $meta);
    }

    #[Test]
    public function it_treats_blank_content_and_bad_dates_as_missing()
    {
        $meta = (new MetaTagParser)->parse(
            '<meta property="og:title" content="  "><meta property="article:published_time" content="soon">'
        );

        $this->assertEquals(new MetaTags, $meta);
    }
}
