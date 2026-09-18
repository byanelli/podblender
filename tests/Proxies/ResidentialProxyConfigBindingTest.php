<?php

namespace Tests\Proxies;

use App\Proxies\Contracts\ResidentialProxyConfig;
use App\Proxies\DataImpulseResidentialProxyConfig;
use App\Proxies\OxylabsResidentialProxyConfig;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The residential proxy provider is chosen in config, and everything that downloads through a proxy asks the
 * container for the interface rather than for a provider by name. These tests cover that choice.
 */
class ResidentialProxyConfigBindingTest extends TestCase
{
    private function useProvider(?string $provider): void
    {
        $this->app->make(Repository::class)->set('services.residential_proxy.provider', $provider);
    }

    #[Test]
    public function it_resolves_the_provider_named_in_config()
    {
        $this->useProvider('oxylabs');

        $this->assertInstanceOf(
            OxylabsResidentialProxyConfig::class,
            $this->app->make(ResidentialProxyConfig::class),
        );

        $this->useProvider('dataimpulse');

        $this->assertInstanceOf(
            DataImpulseResidentialProxyConfig::class,
            $this->app->make(ResidentialProxyConfig::class),
        );
    }

    #[Test]
    public function it_refuses_to_guess_at_a_provider_it_doesnt_recognise()
    {
        $this->useProvider('dataimpluse');

        try {
            $this->app->make(ResidentialProxyConfig::class);
        } catch (InvalidArgumentException $e) {
            // Falling back to the default would leave a typo looking like it worked, and the app using an account
            // nobody chose. So it fails, naming the bad value and the ones that would have worked.
            $this->assertStringContainsString('dataimpluse', $e->getMessage());
            $this->assertStringContainsString('oxylabs', $e->getMessage());
            $this->assertStringContainsString('dataimpulse', $e->getMessage());

            return;
        }

        $this->fail('An unrecognised provider resolved to something instead of failing.');
    }
}
