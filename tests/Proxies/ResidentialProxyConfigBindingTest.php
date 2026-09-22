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
 * Config selects the residential proxy provider, and code that downloads through a proxy resolves the
 * ResidentialProxyConfig interface from the container. These tests cover the binding.
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
            // Falling back to the default would hide a typo and use an account nobody chose. The message includes
            // the bad value and the valid ones.
            $this->assertStringContainsString('dataimpluse', $e->getMessage());
            $this->assertStringContainsString('oxylabs', $e->getMessage());
            $this->assertStringContainsString('dataimpulse', $e->getMessage());

            return;
        }

        $this->fail('An unrecognized provider resolved to something instead of failing.');
    }
}
