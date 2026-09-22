<?php

namespace Tests\Apis\Scraping;

use App\Apis\Scrapfly\Client as ScrapflyClient;
use App\Apis\Scraping\Contracts\Scraper;
use App\Apis\Zyte\Client as ZyteClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScraperBindingTest extends TestCase
{
    #[Test]
    public function it_resolves_the_scraper_that_config_selects()
    {
        config(['services.scraper.provider' => 'zyte']);
        $this->assertInstanceOf(ZyteClient::class, $this->app->make(Scraper::class));

        config(['services.scraper.provider' => 'scrapfly']);
        $this->assertInstanceOf(ScrapflyClient::class, $this->app->make(Scraper::class));
    }

    #[Test]
    public function it_rejects_an_unknown_scraper_provider()
    {
        config(['services.scraper.provider' => 'scrapely']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('scrapely');

        $this->app->make(Scraper::class);
    }
}
