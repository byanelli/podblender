<?php

namespace Tests\Apis\Zyte;

use App\Apis\Scraping\ScraperException;
use App\Apis\Zyte\Client;
use App\Apis\Zyte\DomainForbiddenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    private const KEY = 'zyte-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.zyte.key' => self::KEY]);
    }

    private function client(): Client
    {
        return $this->app->make(Client::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function error(int $status, string $type, string $title): array
    {
        return ['type' => $type, 'title' => $title, 'status' => $status, 'detail' => 'details'];
    }

    #[Test]
    public function it_fetches_rendered_html_with_the_key_as_basic_auth_username()
    {
        Http::fake(['api.zyte.com/*' => Http::response([
            'url'         => 'https://archive.ph/20260922175140/https://www.nytimes.com/x',
            'statusCode'  => 200,
            'browserHtml' => '<html>rendered</html>',
        ])]);

        $result = $this->client()->scrape('https://archive.ph/newest/https://www.nytimes.com/x', renderJs: true);

        $this->assertSame('<html>rendered</html>', $result->content);
        $this->assertSame('https://archive.ph/20260922175140/https://www.nytimes.com/x', $result->finalUrl);
        $this->assertSame(200, $result->statusCode);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.zyte.com/v1/extract'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::KEY.':'))
            && $request['url'] === 'https://archive.ph/newest/https://www.nytimes.com/x'
            && $request['browserHtml'] === true
            && ! isset($request['httpResponseBody']));
    }

    #[Test]
    public function it_decodes_the_base64_body_of_an_unrendered_fetch()
    {
        Http::fake(['api.zyte.com/*' => Http::response([
            'url'              => 'https://www.example.com/x',
            'statusCode'       => 200,
            'httpResponseBody' => base64_encode('<html>plain</html>'),
        ])]);

        $result = $this->client()->scrape('https://www.example.com/x');

        $this->assertSame('<html>plain</html>', $result->content);

        Http::assertSent(fn (Request $request) => $request['httpResponseBody'] === true
            && ! isset($request['browserHtml']));
    }

    #[Test]
    public function it_reports_the_target_status_the_api_relays()
    {
        Http::fake(['api.zyte.com/*' => Http::response([
            'url'         => 'https://archive.ph/newest/https://www.example.com/never',
            'statusCode'  => 404,
            'browserHtml' => '<html>No results</html>',
        ])]);

        $this->assertSame(404, $this->client()->scrape('https://archive.ph/newest/https://www.example.com/never', true)->statusCode);
    }

    #[Test]
    public function it_remembers_a_forbidden_domain_for_thirty_days()
    {
        Http::fake(['api.zyte.com/*' => Http::response(
            $this->error(451, '/download/domain-forbidden', 'Domain Forbidden'),
            451,
        )]);

        try {
            $this->client()->scrape('https://www.theguardian.com/some-article');
            $this->fail('Expected a DomainForbiddenException.');
        } catch (DomainForbiddenException) {
        }

        // The second request for the domain doesn't reach Zyte.
        $this->expectException(DomainForbiddenException::class);

        try {
            $this->client()->scrape('https://theguardian.com/another-article');
        } finally {
            Http::assertSentCount(1);
            $this->assertTrue(Cache::has('zyte:forbidden-domain:theguardian.com'));
        }
    }

    #[Test]
    public function it_forgets_a_forbidden_domain_after_thirty_days()
    {
        Http::fake(['api.zyte.com/*' => Http::sequence()
            ->push($this->error(451, '/download/domain-forbidden', 'Domain Forbidden'), 451)
            ->push(['url' => 'https://www.theguardian.com/x', 'statusCode' => 200, 'httpResponseBody' => base64_encode('ok')]),
        ]);

        try {
            $this->client()->scrape('https://www.theguardian.com/x');
        } catch (DomainForbiddenException) {
        }

        $this->travel(31)->days();

        $this->assertSame('ok', $this->client()->scrape('https://www.theguardian.com/x')->content);
    }

    #[Test]
    public function it_throws_on_other_api_errors_without_caching_the_domain()
    {
        Http::fake(['api.zyte.com/*' => Http::response(
            $this->error(520, '/download/temporary-error', 'Temporary Download Error'),
            520,
        )]);

        try {
            $this->client()->scrape('https://www.example.com/x');
            $this->fail('Expected a ScraperException.');
        } catch (ScraperException $e) {
            $this->assertStringContainsString('520', $e->getMessage());
            $this->assertStringContainsString('/download/temporary-error', $e->getMessage());
        }

        $this->assertFalse(Cache::has('zyte:forbidden-domain:example.com'));
    }

    #[Test]
    public function it_retries_a_dropped_connection_then_gives_up()
    {
        // A request whose fake throws isn't recorded, so count attempts here.
        $attempts = 0;
        Http::fake(['api.zyte.com/*' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 56');
        }]);

        $this->expectException(ScraperException::class);

        try {
            $this->client()->scrape('https://www.example.com/x');
        } finally {
            $this->assertSame(3, $attempts);
        }
    }
}
