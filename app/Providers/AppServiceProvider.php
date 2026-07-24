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
use App\Proxies\OxylabsResidentialProxyConfig;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

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

        $this->app->bind(ResidentialProxyConfig::class, OxylabsResidentialProxyConfig::class);

        $this->registerDownloadRateLimiter();

        $this->app->make(BroadcastManager::class)->routes();
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
