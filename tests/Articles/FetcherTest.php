<?php

namespace Tests\Articles;

use App\Articles\Contracts\Fetcher;
use App\Proxies\Contracts\ResidentialProxyConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FetcherTest extends TestCase
{
    public const PROXY_URL = 'http://proxy.test:1234';

    protected function setUp(): void
    {
        parent::setUp();

        // A stub proxy config with a fixed URL so we can assert the proxy option
        // exactly, rather than fighting the real config's random session id.
        $this->app->bind(ResidentialProxyConfig::class, fn () => new readonly class implements ResidentialProxyConfig
        {
            public function getUrlForDownload(): string
            {
                return FetcherTest::PROXY_URL;
            }

            public function requiresInsecureTls(): bool
            {
                return false;
            }
        });
    }

    private function fetcher(): Fetcher
    {
        return $this->app->make(Fetcher::class);
    }

    #[Test]
    public function it_fetches_a_page_directly_with_a_browser_user_agent()
    {
        Http::fake(['*' => Http::response('<html>direct</html>')]);

        $body = $this->fetcher()->fetchDirect('https://theonion.com/some-article');

        $this->assertEquals('<html>direct</html>', $body);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://theonion.com/some-article'
            && $request->hasHeader('User-Agent', config('articles.user_agent')));
    }

    #[Test]
    public function it_throws_when_a_direct_fetch_fails()
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(RequestException::class);

        $this->fetcher()->fetchDirect('https://theonion.com/some-article');
    }

    #[Test]
    public function it_fetches_the_newest_archive_snapshot_through_the_residential_proxy()
    {
        $capturedOptions = null;

        Http::fake(function ($request, $options) use (&$capturedOptions) {
            $capturedOptions = $options;

            return Http::response('<html>snapshot</html>');
        });

        $body = $this->fetcher()->fetchFromArchive('https://theonion.com/some-article');

        $this->assertEquals('<html>snapshot</html>', $body);
        $this->assertSame(self::PROXY_URL, $capturedOptions['proxy'] ?? null);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://archive.ph/newest/https://theonion.com/some-article');
    }

    #[Test]
    public function it_throws_when_no_archive_snapshot_exists()
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->expectException(RuntimeException::class);

        $this->fetcher()->fetchFromArchive('https://theonion.com/some-article');
    }
}
