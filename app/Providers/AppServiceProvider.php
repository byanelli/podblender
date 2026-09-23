<?php

namespace App\Providers;

use App\Apis\Ffmpeg\Client as FfmpegClient;
use App\Apis\Ffmpeg\Contracts\Client as FfmpegClientContract;
use App\Apis\Resend\Client as ResendClient;
use App\Apis\Resend\Contracts\Client as ResendClientContract;
use App\Apis\Scrapfly\Client as ScrapflyClient;
use App\Apis\Scraping\Contracts\Scraper;
use App\Apis\Tts\Contracts\Client as TtsClientContract;
use App\Apis\Tts\GeminiClient as TtsClient;
use App\Apis\YouTubeData\Client as YouTubeDataClient;
use App\Apis\YouTubeData\Contracts\Client as YouTubeDataClientContract;
use App\Apis\Zyte\Client as ZyteClient;
use App\Articles\Contracts\Fetcher as FetcherContract;
use App\Articles\Contracts\Reader as ReaderContract;
use App\Articles\Fetcher;
use App\Articles\Reader;
use App\Covers\Contracts\CoverGenerator as CoverGeneratorContract;
use App\Covers\GdCoverGenerator;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Proxies\Contracts\ResidentialProxyConfig;
use App\Proxies\DataImpulseResidentialProxyConfig;
use App\Proxies\OxylabsResidentialProxyConfig;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        Model::shouldBeStrict();
        Model::preventLazyLoading();
        Date::use(CarbonImmutable::class);

        $this->app->bind(TtsClientContract::class, TtsClient::class);
        $this->app->bind(FfmpegClientContract::class, FfmpegClient::class);
        $this->app->bind(YouTubeDataClientContract::class, YouTubeDataClient::class);
        $this->app->bind(ResendClientContract::class, ResendClient::class);
        $this->app->bind(Scraper::class, fn () => $this->app->make($this->scraperClass()));

        $this->app->bind(CoverGeneratorContract::class, GdCoverGenerator::class);

        $this->app->bind(FetcherContract::class, Fetcher::class);
        $this->app->bind(ReaderContract::class, Reader::class);

        $this->app->bind(
            ResidentialProxyConfig::class,
            fn () => $this->app->make($this->residentialProxyConfigClass()),
        );

        $this->registerDownloadRateLimiter();

        $this->app->make(BroadcastManager::class)->routes();
    }

    /**
     * The residential proxy implementation that RESIDENTIAL_PROXY_PROVIDER selects. Only that provider's credentials
     * are read.
     *
     * An unrecognized name throws. Falling back to a default would let a typo in RESIDENTIAL_PROXY_PROVIDER select a
     * provider account the operator didn't choose.
     *
     * @return class-string<ResidentialProxyConfig>
     */
    private function residentialProxyConfigClass(): string
    {
        $provider = $this->app->make(Config::class)->get('services.residential_proxy.provider');

        return match ($provider) {
            'oxylabs'     => OxylabsResidentialProxyConfig::class,
            'dataimpulse' => DataImpulseResidentialProxyConfig::class,
            default       => throw new InvalidArgumentException(sprintf(
                'Unknown residential proxy provider [%s]. RESIDENTIAL_PROXY_PROVIDER must be one of: oxylabs, '
                .'dataimpulse.',
                is_string($provider) ? $provider : get_debug_type($provider),
            )),
        };
    }

    /**
     * The scraping service that SCRAPER_PROVIDER selects. An unrecognized name throws, for the same reason as
     * residentialProxyConfigClass().
     *
     * @return class-string<Scraper>
     */
    private function scraperClass(): string
    {
        $provider = $this->app->make(Config::class)->get('services.scraper.provider');

        return match ($provider) {
            'scrapfly' => ScrapflyClient::class,
            'zyte'     => ZyteClient::class,
            default    => throw new InvalidArgumentException(sprintf(
                'Unknown scraper provider [%s]. SCRAPER_PROVIDER must be one of: scrapfly, zyte.',
                is_string($provider) ? $provider : get_debug_type($provider),
            )),
        };
    }

    /**
     * Register the rate limiter used by App\Jobs\DownloadAndStoreAudioClip: one download per platform per N minutes.
     * N is configurable because the rate YouTube tolerates changes over time.
     */
    private function registerDownloadRateLimiter(): void
    {
        $minutes = $this->app->make(Config::class)->get('downloads.minutes_between_downloads');

        $this->app->make(RateLimiter::class)->for(
            DownloadAndStoreAudioClip::THROTTLE,
            fn (DownloadAndStoreAudioClip $job) => Limit::perMinutes($minutes, 1)->by($job->throttleKey()),
        );
    }
}
