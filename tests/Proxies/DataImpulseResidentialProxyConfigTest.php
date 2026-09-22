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

        // Without a session, the gateway uses a different address for every request, and a download whose metadata
        // and media requests come from two addresses is refused. The semicolons between parameters are
        // percent-encoded because they are in the URL's userinfo.
        $this->assertMatchesRegularExpression('/%3Bsessid\.\w+%3B/', $url);

        // Measured on port 823: a session kept one exit address through 55 minutes and had a new one at 60.
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

        // DataImpulse's documentation writes country codes in lowercase. Oxylabs takes capitals, so .env may have
        // either.
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

            // DataImpulse refuses "cr." with no value, and the download fails with a 503. With the parameter omitted,
            // DataImpulse chooses the exit country.
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

        // Downloading everything from one address gets that address blocked, so each download requests a new one.
        $this->assertCount(5, $sessions->unique(), 'Two downloads were given the same session, and so the same IP.');
    }

    #[Test]
    public function it_escapes_credentials_that_would_otherwise_change_the_url()
    {
        $config = $this->app->make(Repository::class);

        $config->set('services.dataimpulse.residential.user', 'someuser');
        $config->set('services.dataimpulse.residential.country', 'us');

        // DataImpulse generates the passwords, and they often contain characters reserved in URLs.
        $config->set('services.dataimpulse.residential.password', 'pa+ss:word@example');

        $url = $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();

        $this->assertStringContainsString('pa%2Bss%3Aword%40example', $url);

        // An unescaped @ in the password would change the host.
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
            // A bare "DATAIMPULSE_USERNAME=" in .env produces an empty string, which is more likely than a missing key.
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

        // The message includes the missing env variable's name. A TypeError from building the username would look
        // like a bug in this class.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DATAIMPULSE_USERNAME');

        $this->app->make(DataImpulseResidentialProxyConfig::class)->getUrlForDownload();
    }

    #[Test]
    public function it_leaves_tls_alone()
    {
        // DataImpulse tunnels with CONNECT, so YouTube's certificate can still be verified.
        $this->assertFalse($this->makeConfig()->requiresInsecureTls());
    }
}
