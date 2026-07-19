<?php

namespace Tests\Apis\Scrapfly;

use App\Apis\Scrapfly\Contracts\Client;
use App\Apis\Scrapfly\ScrapflyException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    private const KEY = 'sk-super-secret-scrapfly-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.scrapfly.key' => self::KEY]);
    }

    private function client(): Client
    {
        return $this->app->make(Client::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function successJson(array $overrides = []): array
    {
        return array_replace_recursive([
            'result' => [
                'content' => '<html>scraped</html>',
                'url' => 'https://archive.is/CLBwm',
                'status_code' => 200,
                'success' => true,
                // Cost is an OBJECT, not a scalar — the client must not choke on it.
                'cost' => ['total' => 30, 'details' => [['amount' => 30]]],
            ],
            'context' => ['cost' => ['total' => 30]],
        ], $overrides);
    }

    #[Test]
    public function it_scrapes_through_asp_with_the_expected_query_and_parses_the_result()
    {
        Http::fake(['api.scrapfly.io/*' => Http::response($this->successJson())]);

        $result = $this->client()->scrape('https://archive.ph/https://www.nytimes.com/x', renderJs: true);

        $this->assertSame('<html>scraped</html>', $result->content);
        $this->assertSame('https://archive.is/CLBwm', $result->finalUrl);
        $this->assertSame(200, $result->statusCode);
        $this->assertTrue($result->success);

        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.scrapfly.io/scrape')
                && ($query['asp'] ?? null) === 'true'
                && ($query['render_js'] ?? null) === 'true'
                && ($query['country'] ?? null) === 'us'
                && ($query['url'] ?? null) === 'https://archive.ph/https://www.nytimes.com/x'
                && ($query['key'] ?? null) === self::KEY;
        });
    }

    #[Test]
    public function it_sends_render_js_false_when_not_rendering()
    {
        Http::fake(['api.scrapfly.io/*' => Http::response($this->successJson())]);

        $this->client()->scrape('https://archive.ph/x');

        Http::assertSent(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['render_js'] ?? null) === 'false';
        });
    }

    #[Test]
    public function it_throws_when_scrapfly_reports_an_unsuccessful_scrape()
    {
        Http::fake(['api.scrapfly.io/*' => Http::response($this->successJson([
            'result' => ['success' => false],
        ]))]);

        $this->expectException(ScrapflyException::class);

        $this->client()->scrape('https://archive.ph/x');
    }

    #[Test]
    public function it_throws_when_scrapfly_returns_a_non_2xx_status()
    {
        Http::fake(['api.scrapfly.io/*' => Http::response('nope', 500)]);

        $this->expectException(ScrapflyException::class);

        $this->client()->scrape('https://archive.ph/x');
    }

    #[Test]
    public function it_retries_a_dropped_connection_then_succeeds()
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('cURL error 56: Recv failure');
            }

            return Http::response($this->successJson());
        });

        $result = $this->client()->scrape('https://archive.ph/x');

        $this->assertSame('<html>scraped</html>', $result->content);
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function it_gives_up_after_exhausting_connection_retries()
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 56: Recv failure'));

        $this->expectException(ScrapflyException::class);

        $this->client()->scrape('https://archive.ph/x');
    }

    #[Test]
    public function it_keeps_the_api_key_out_of_a_thrown_exception_message()
    {
        // A ConnectionException message would echo the full request URL, key and
        // all. The sanitized ScrapflyException must never carry it.
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 56 for https://api.scrapfly.io/scrape?key='.self::KEY.'&url=x'
        ));

        try {
            $this->client()->scrape('https://archive.ph/x');
            $this->fail('Expected a ScrapflyException.');
        } catch (ScrapflyException $e) {
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
            $this->assertStringNotContainsString(self::KEY, (string) $e);
        }
    }
}
