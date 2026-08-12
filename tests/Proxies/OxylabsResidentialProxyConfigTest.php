<?php

namespace Tests\Proxies;

use App\Proxies\OxylabsResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OxylabsResidentialProxyConfigTest extends TestCase
{
    private function makeConfig(): OxylabsResidentialProxyConfig
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.oxylabs.residential.user', 'someuser');
        $config->set('services.oxylabs.residential.password', 'somepassword');
        $config->set('services.oxylabs.residential.country', 'US');

        return $this->app->make(OxylabsResidentialProxyConfig::class);
    }

    #[Test]
    public function it_asks_oxylabs_to_hold_one_address_for_the_whole_download()
    {
        $url = $this->makeConfig()->getUrlForDownload();

        // Without a session, Oxylabs hands out a different address on every request, and a download that fetches its
        // metadata and its media from two addresses is refused.
        $this->assertMatchesRegularExpression('/-sessid-\w+-/', $url);

        // Their default session is 10 minutes. Long downloads outlive that, and an address that changes midway is
        // exactly what the session is there to prevent.
        $this->assertStringContainsString('-sesstime-60:', $url);

        $this->assertStringStartsWith('http://customer-someuser-cc-US-sessid-', $url);
        $this->assertStringEndsWith('@pr.oxylabs.io:7777', $url);
    }

    #[Test]
    public function it_uses_a_different_address_for_every_download()
    {
        $config = $this->makeConfig();

        $sessions = collect(range(1, 5))
            ->map(fn () => $config->getUrlForDownload())
            ->map(fn (string $url) => preg_match('/-sessid-(\w+)-/', $url, $m) ? $m[1] : null);

        // Downloading everything from one address is what gets us blocked, so consecutive downloads must ask for
        // different ones.
        $this->assertCount(5, $sessions->unique(), 'Two downloads were given the same session, and so the same IP.');
    }

    #[Test]
    public function it_escapes_credentials_that_would_otherwise_change_the_url()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.oxylabs.residential.user', 'someuser');
        $config->set('services.oxylabs.residential.country', 'US');

        // Oxylabs generates passwords, and they routinely contain characters that mean something inside a URL.
        $config->set('services.oxylabs.residential.password', 'pa+ss:word@example');

        $url = $this->app->make(OxylabsResidentialProxyConfig::class)->getUrlForDownload();

        $this->assertStringContainsString('pa%2Bss%3Aword%40example', $url);

        // The host has to survive an @ in the password.
        $this->assertStringEndsWith('@pr.oxylabs.io:7777', $url);
    }

    #[Test]
    public function it_reports_itself_configured_only_when_it_has_both_credentials()
    {
        $config = $this->app->make(Repository::class);
        $proxy = $this->app->make(OxylabsResidentialProxyConfig::class);

        $cases = [
            'both present'   => ['someuser', 'somepassword', true],
            'no password'    => ['someuser', null, false],
            'no user'        => [null, 'somepassword', false],
            'neither'        => [null, null, false],
            // An account that was half-filled-in is not an account. Empty strings are what a .env with bare
            // "OXYLABS_USERNAME=" produces, which is likelier than the key being absent altogether.
            'empty strings'  => ['', '', false],
            'empty password' => ['someuser', '', false],
        ];

        foreach ($cases as $name => [$user, $password, $expected]) {
            $config->set('services.oxylabs.residential.user', $user);
            $config->set('services.oxylabs.residential.password', $password);

            $this->assertSame($expected, $proxy->isConfigured(), "Wrong verdict for: $name");
        }
    }

    #[Test]
    public function it_refuses_to_build_a_url_without_credentials()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.oxylabs.residential.user', null);
        $config->set('services.oxylabs.residential.password', null);

        // Better than a TypeError from somewhere inside the username, which reads like a bug in this class rather
        // than a machine that never had an Oxylabs account.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OXYLABS_USERNAME');

        $this->app->make(OxylabsResidentialProxyConfig::class)->getUrlForDownload();
    }

    #[Test]
    public function it_leaves_tls_alone()
    {
        // Oxylabs tunnels with CONNECT, so we can still verify YouTube's certificate and shouldn't be turning that
        // check off.
        $this->assertFalse($this->makeConfig()->requiresInsecureTls());
    }
}
