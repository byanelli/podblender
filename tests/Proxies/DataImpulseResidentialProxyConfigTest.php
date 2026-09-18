<?php

namespace Tests\Proxies;

use App\Proxies\DataImpulseResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DataImpulseResidentialProxyConfigTest extends TestCase
{
    private function makeConfig(): DataImpulseResidentialProxyConfig
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', 'someuser');
        $config->set('services.dataimpulse.residential.password', 'somepassword');
        $config->set('services.dataimpulse.residential.country', 'us');

        return $this->app->make(DataImpulseResidentialProxyConfig::class);
    }

    #[Test]
    public function it_asks_dataimpulse_to_hold_one_address_for_the_whole_download()
    {
        $url = $this->makeConfig()->getUrlForDownload();

        // Without a session, the gateway hands out a different address on every request, and a download that fetches
        // its metadata and its media from two addresses is refused. The semicolons between parameters arrive
        // percent-encoded, which is how they're meant to travel inside a URL's userinfo.
        $this->assertMatchesRegularExpression('/%3Bsessid\.\w+%3B/', $url);

        // Sixty minutes, which we measured the gateway honouring on port 823: the exit address held for the full
        // hour and changed once it was up. An address that changes midway through a download is exactly what the
        // session is there to prevent.
        $this->assertStringContainsString('%3Bsessttl.60:', $url);

        $this->assertStringStartsWith('http://someuser__cr.us%3Bsessid.', $url);
        $this->assertStringEndsWith('@gw.dataimpulse.com:823', $url);
    }

    #[Test]
    public function it_sends_the_country_in_the_lowercase_form_dataimpulse_uses()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', 'someuser');
        $config->set('services.dataimpulse.residential.password', 'somepassword');

        // Every country code in DataImpulse's documentation is lowercase, and the rest of this app writes them the
        // way Oxylabs wants them, in capitals. Whichever way it's written in .env, DataImpulse gets lowercase.
        $config->set('services.dataimpulse.residential.country', 'DE');

        $url = $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();

        $this->assertStringStartsWith('http://someuser__cr.de%3B', $url);
    }

    #[Test]
    public function it_leaves_the_country_out_when_none_is_configured()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', 'someuser');
        $config->set('services.dataimpulse.residential.password', 'somepassword');

        foreach (['no country' => null, 'blank country' => '  '] as $name => $country) {
            $config->set('services.dataimpulse.residential.country', $country);

            $url = $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();

            // "cr." with nothing after it is a parameter with no value, which DataImpulse refuses. Saying nothing
            // lets them pick the exit country themselves, which is a working download rather than a 503.
            $this->assertStringNotContainsString('cr.', $url, "Sent an empty country for: $name");

            $this->assertStringStartsWith('http://someuser__sessid.', $url, "Wrong username for: $name");
            $this->assertStringContainsString('%3Bsessttl.60:', $url, "Lost the session length for: $name");
        }
    }

    #[Test]
    public function it_uses_a_different_address_for_every_download()
    {
        $config = $this->makeConfig();

        $sessions = collect(range(1, 5))
            ->map(fn () => $config->getUrlForDownload())
            ->map(fn (string $url) => preg_match('/%3Bsessid\.(\w+)%3B/', $url, $m) ? $m[1] : null);

        // Downloading everything from one address is what gets us blocked, so consecutive downloads must ask for
        // different ones.
        $this->assertCount(5, $sessions->unique(), 'Two downloads were given the same session, and so the same IP.');
    }

    #[Test]
    public function it_escapes_credentials_that_would_otherwise_change_the_url()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', 'someuser');
        $config->set('services.dataimpulse.residential.country', 'us');

        // DataImpulse generates passwords, and they routinely contain characters that mean something inside a URL.
        $config->set('services.dataimpulse.residential.password', 'pa+ss:word@example');

        $url = $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();

        $this->assertStringContainsString('pa%2Bss%3Aword%40example', $url);

        // The host has to survive an @ in the password.
        $this->assertStringEndsWith('@gw.dataimpulse.com:823', $url);
    }

    #[Test]
    public function it_reports_itself_configured_only_when_it_has_both_credentials()
    {
        $config = $this->app->make(Repository::class);
        $proxy = $this->app->make(DataImpulseResidentialProxyConfig::class);

        $cases = [
            'both present'   => ['someuser', 'somepassword', true],
            'no password'    => ['someuser', null, false],
            'no user'        => [null, 'somepassword', false],
            'neither'        => [null, null, false],
            // An account that was half-filled-in is not an account. Empty strings are what a .env with bare
            // "DATAIMPULSE_USERNAME=" produces, which is likelier than the key being absent altogether.
            'empty strings'  => ['', '', false],
            'empty password' => ['someuser', '', false],
        ];

        foreach ($cases as $name => [$user, $password, $expected]) {
            $config->set('services.dataimpulse.residential.user', $user);
            $config->set('services.dataimpulse.residential.password', $password);

            $this->assertSame($expected, $proxy->isConfigured(), "Wrong verdict for: $name");
        }
    }

    #[Test]
    public function it_refuses_to_build_a_url_without_credentials()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', null);
        $config->set('services.dataimpulse.residential.password', null);

        // Better than a TypeError from somewhere inside the username, which reads like a bug in this class rather
        // than a machine that never had a DataImpulse account.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATAIMPULSE_USERNAME');

        $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();
    }

    #[Test]
    public function it_leaves_tls_alone()
    {
        // DataImpulse tunnels with CONNECT, so we can still verify YouTube's certificate and shouldn't be turning
        // that check off.
        $this->assertFalse($this->makeConfig()->requiresInsecureTls());
    }
}
