<?php

namespace Tests\InboundEmails;

use App\Apis\Resend\ReceivedEmail;
use App\InboundEmails\LinkExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LinkExtractorTest extends TestCase
{
    #[Test]
    public function it_prefers_the_subject_over_the_body()
    {
        $email = new ReceivedEmail(
            subject: 'https://example.com/subject',
            text: 'https://example.com/text',
            html: '<p>https://example.com/html</p>',
        );

        $this->assertEquals('https://example.com/subject', (new LinkExtractor)->firstLink($email));
    }

    #[Test]
    public function it_takes_the_first_url_in_the_text_body()
    {
        $email = new ReceivedEmail(
            subject: 'Worth a listen',
            text: "Here:\nhttps://www.youtube.com/watch?v=abc123\n\nAlso https://example.com/other",
            html: null,
        );

        $this->assertEquals('https://www.youtube.com/watch?v=abc123', (new LinkExtractor)->firstLink($email));
    }

    #[Test]
    public function it_reads_the_html_body_when_there_is_no_text_body()
    {
        $email = new ReceivedEmail(
            subject: null,
            text: null,
            html: '<html><head><link href="https://fonts.example.com/css"><style>body{background:url(https://example.com/bg.png)}</style></head>'
                .'<body><p>Read this:</p><p>https://example.com/article?a=1&amp;b=2</p></body></html>',
        );

        $this->assertEquals('https://example.com/article?a=1&b=2', (new LinkExtractor)->firstLink($email));
    }

    #[Test]
    public function it_falls_back_to_a_link_target_in_the_html_body()
    {
        $email = new ReceivedEmail(
            subject: 'An article',
            text: null,
            html: '<p><a href="https://example.com/story?id=1&amp;ref=mail">Read the story</a></p>',
        );

        $this->assertEquals('https://example.com/story?id=1&ref=mail', (new LinkExtractor)->firstLink($email));
    }

    #[Test]
    public function it_returns_null_when_the_email_has_no_link()
    {
        $email = new ReceivedEmail(subject: 'Hello', text: 'No links here.', html: '<p>None here either.</p>');

        $this->assertNull((new LinkExtractor)->firstLink($email));
    }

    #[Test]
    #[DataProvider('punctuatedUrls')]
    public function it_drops_punctuation_that_ends_a_sentence(string $text, string $expected)
    {
        $email = new ReceivedEmail(subject: null, text: $text, html: null);

        $this->assertEquals($expected, (new LinkExtractor)->firstLink($email));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function punctuatedUrls(): array
    {
        return [
            'full stop'                => ['See https://example.com/a.', 'https://example.com/a'],
            'comma'                    => ['https://example.com/a, and more', 'https://example.com/a'],
            'in parentheses'           => ['(see https://example.com/a)', 'https://example.com/a'],
            'balanced parentheses'     => ['https://en.wikipedia.org/wiki/Mercury_(planet)', 'https://en.wikipedia.org/wiki/Mercury_(planet)'],
            'in angle brackets'        => ['<https://example.com/a>', 'https://example.com/a'],
            'question mark then prose' => ['Is it https://example.com/a?', 'https://example.com/a'],
        ];
    }
}
