<?php

namespace Tests\Platforms;

use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Web;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\FakesWhisper;
use Tests\TestCase;

class WebTest extends TestCase
{
    use FakesWhisper;

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

        $this->fakeWhisper();

        /** @var Web $web */
        $web = $this->app->make(Web::class);

        $mp3 = $web->downloadAudio('https://theopenpress.com/harvest-festival');

        $this->assertFileExists($mp3);
        $this->assertStringContainsString('harvest festival', (string) file_get_contents($mp3));
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
