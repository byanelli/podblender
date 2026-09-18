<?php

namespace App\Providers;

use App\Apis\Ffmpeg\Client as FfmpegClient;
use App\Apis\Ffmpeg\Contracts\Client as FfmpegClientContract;
use App\Apis\Scrapfly\Client as ScrapflyClient;
use App\Apis\Scrapfly\Contracts\Client as ScrapflyClientContract;
use App\Apis\Tts\Contracts\Client as TtsClientContract;
use App\Apis\Tts\GeminiClient as TtsClient;
use App\Apis\YouTubeData\Client as YouTubeDataClient;
use App\Apis\YouTubeData\Contracts\Client as YouTubeDataClientContract;
use App\Articles\Contracts\Fetcher as FetcherContract;
use App\Articles\Contracts\Reader as ReaderContract;
use App\Articles\Fetcher;
use App\Articles\Reader;
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
        $this->app->bind(ScrapflyClientContract::class, ScrapflyClient::class);

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
     * Which residential proxy implementation this install uses, decided by config rather than by editing this file.
     * Both providers cost money and need an account, so an install has at most one of them, and the app only ever
     * reads the credentials of the one named here.
     *
     * An unrecognised name is an error and not a reason to fall back to the default: a typo in
     * RESIDENTIAL_PROXY_PROVIDER would otherwise leave the app quietly using an account the operator didn't pick,
     * which shows up much later as a bill or a download that keeps failing.
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
     * Register the limiter that App\Jobs\DownloadAndStoreAudioClip uses to leave a gap between one download and the
     * next. One download per N minutes, where N is configurable because the right value is whatever YouTube is
     * tolerating this month.
     */
    private function registerDownloadRateLimiter(): void
    {
        $minutes = $this->app->make(Config::class)->get('downloads.minutes_between_downloads');

        $this->app->make(RateLimiter::class)->for(
            DownloadAndStoreAudioClip::THROTTLE,
            fn () => Limit::perMinutes($minutes, 1),
        );
    }
}
