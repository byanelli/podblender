<?php

namespace Tests\Platforms;

use App\Apis\Tts\Contracts\Client;
use App\Articles\Article;
use App\Articles\Contracts\Reader;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Web;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesTts;
use Tests\TestCase;

class WebTest extends TestCase
{
    use FakesTts;

    private function articleHtml(): string
    {
        return (string) file_get_contents(__DIR__.'/../Articles/fixtures/clean-full.html');
    }

    #[Test]
    public function it_gets_clip_metadata()
    {
        Http::fake(['*' => Http::response($this->articleHtml())]);

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $metadata = $web->getClipMetadata($url = 'https://theopenpress.com/harvest-festival');

        $this->assertEquals($url, $metadata->canonicalUrl);
        $this->assertEquals('A Complete, Freely Readable Article', $metadata->title);
        $this->assertEquals('Article by Freely Available', $metadata->description);
        $this->assertEquals(CarbonImmutable::parse('2023-03-03T12:00:00+00:00'), $metadata->publishedAt);
        $this->assertEquals('https://theopenpress.com', $metadata->source->canonicalUrl);
        $this->assertEquals('The Open Press', $metadata->source->name);
    }

    #[Test]
    public function it_estimates_download_time_from_the_tts_backend()
    {
        Http::fake(['*' => Http::response($this->articleHtml())]);

        $narration = $this->fakeTts();

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $metadata = $web->getClipMetadata('https://theopenpress.com/harvest-festival');

        // The estimate is the TTS backend's narration estimate plus 30s of
        // fetch overhead.
        $this->assertEquals($narration + 30, $metadata->estimatedDownloadTime);
    }

    #[Test]
    public function it_estimates_a_longer_download_for_a_longer_article()
    {
        $short = 'A short article. '.str_repeat('Words about the harvest. ', 10);
        $long = 'A long article. '.str_repeat('Words about the harvest. ', 2000);

        /** @var Client $tts */
        $tts = $this->app->make(Client::class);

        // Uses the real backend. An article that needs many TTS requests must
        // get a larger estimate than one that fits in a single request,
        // because the job timeout is derived from the estimate.
        $this->assertGreaterThan(
            $tts->estimateNarrationTime($short),
            $tts->estimateNarrationTime($long),
        );

        $this->assertGreaterThan(0, $tts->estimateNarrationTime($short));
    }

    #[Test]
    public function it_gets_source_metadata()
    {
        $name = 'The Onion';
        $url = 'https://theonion.com';

        Http::fake([$url => Http::response("<title>$name</title>")]);

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $metadata = $web->getSourceMetadata($url);

        $this->assertEquals($name, $metadata->name);
        $this->assertEquals($url, $metadata->canonicalUrl);
    }

    #[Test]
    public function it_downloads_audio()
    {
        Http::fake(['*' => Http::response($this->articleHtml())]);

        $this->fakeTts();

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $mp3 = $web->downloadAudio('https://theopenpress.com/harvest-festival')->path;

        $this->assertFileExists($mp3);
        $this->assertStringContainsString('harvest festival', (string) file_get_contents($mp3));
    }

    private function fakeReader(Article $article): void
    {
        $this->app->instance(Reader::class, new readonly class($article) implements Reader
        {
            public function __construct(private Article $article) {}

            public function read(string $url): Article
            {
                return $this->article;
            }
        });
    }

    #[Test]
    public function it_introduces_the_article_before_narrating_it()
    {
        $this->fakeReader(new Article(
            url: 'https://riversidegazette.com/park',
            title: 'City Council Approves Riverside Park.',
            publisher: 'The Riverside Gazette',
            publicationDate: CarbonImmutable::parse('2021-06-15T09:30:00+00:00'),
            authors: ['Ada Reporter', 'Ben Writer', 'Cy Editor'],
            text: 'The vote was unanimous.',
        ));

        $this->fakeTts();

        $mp3 = $this->app->make(Web::class)->downloadAudio('https://riversidegazette.com/park')->path;

        // The fake TTS backend writes the text it was given.
        $this->assertEquals(
            'City Council Approves Riverside Park... By Ada Reporter, Ben Writer and Cy Editor... '
            .'Published in The Riverside Gazette on June 15, 2021... The vote was unanimous.',
            file_get_contents($mp3),
        );
    }

    #[Test]
    public function it_leaves_unknown_details_out_of_the_introduction()
    {
        $this->fakeReader(new Article(
            url: 'https://example.com/almanac',
            title: 'The Undated Almanac',
            publisher: 'The Timeless Register',
            publicationDate: null,
            authors: [],
            text: 'No one knows when this was written.',
        ));

        $this->fakeTts();

        $web = $this->app->make(Web::class);

        $this->assertEquals(
            'The Undated Almanac... Published in The Timeless Register... No one knows when this was written.',
            file_get_contents($web->downloadAudio('https://example.com/almanac')->path),
        );

        // The feed still needs a date for the clip.
        $this->assertTrue(
            CarbonImmutable::now()->diffInSeconds($web->getClipMetadata('https://example.com/almanac')->publishedAt, absolute: true) < 60
        );
    }

    #[Test]
    public function it_wraps_a_metadata_failure_in_a_platform_exception()
    {
        Http::fake(fn () => throw new RuntimeException('boom'));

        $this->expectException(PlatformException::class);

        $this->app->make(Web::class)->getClipMetadata('https://theonion.com/some-article');
    }

    #[Test]
    public function it_wraps_a_download_failure_in_a_platform_exception()
    {
        Http::fake(fn () => throw new RuntimeException('boom'));

        $this->expectException(PlatformException::class);

        $this->app->make(Web::class)->downloadAudio('https://theonion.com/some-article');
    }
}
