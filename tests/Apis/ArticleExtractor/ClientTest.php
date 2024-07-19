<?php

namespace Tests\Apis\ArticleExtractor;

use App\Apis\ArticleExtractor\Client;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    #[Test]
    public function it_gets_an_article()
    {
        Http::fake(['*' => Http::response([
            [
                'title' => $title = 'Article Headline',
                'publisher' => $publisher = 'Some News Outlet',
                'author' => $authors = ['Author1', 'Author2'],
                'text' => $text = 'Lorem ipsum dolor sit amet.',
                'date' => ($date = CarbonImmutable::parse('2020-01-01'))->format(DateTimeInterface::RFC3339),
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $article = $client->getArticle($url = 'https://news.com/breaking-news');

        $this->assertEquals($url, $article->url);
        $this->assertEquals($title, $article->title);
        $this->assertEquals($publisher, $article->publisher);
        $this->assertEquals($authors, $article->authors);
        $this->assertEquals($text, $article->text);
        $this->assertEquals($date, $article->publicationDate);
    }

    #[Test]
    public function it_gets_author_names_from_url_slugs()
    {
        Http::fake(['*' => Http::response([
            [
                'title' => 'Article Headline',
                'publisher' => 'Some News Outlet',
                'author' => ['https://news.com/authors/john-doe', 'https://news.com/authors/jane-doe'],
                'text' => 'Lorem ipsum dolor sit amet.',
                'date' => ($date = CarbonImmutable::parse('2020-01-01'))->format(DateTimeInterface::RFC3339),
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $article = $client->getArticle('https://news.com/breaking-news');

        $this->assertEquals(['John Doe', 'Jane Doe'], $article->authors);
    }

    #[Test]
    public function it_uses_domain_name_when_publisher_is_null()
    {
        Http::fake(['*' => Http::response([
            [
                'title' => 'Article Headline',
                'publisher' => null,
                'author' => ['Author1'],
                'text' => 'Lorem ipsum dolor sit amet.',
                'date' => ($date = CarbonImmutable::parse('2020-01-01'))->format(DateTimeInterface::RFC3339),
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $article = $client->getArticle('https://www.nytimes.com/breaking-news');

        $this->assertEquals('nytimes.com', $article->publisher);
    }
}
