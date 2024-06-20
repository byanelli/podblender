<?php

namespace App\Providers;

use App\Apis\Ffmpeg\Client as FfmpegClient;
use App\Apis\Ffmpeg\Contracts\Client as FfmpegClientContract;
use App\Apis\Whisper\Client as WhisperClient;
use App\Apis\Whisper\Contracts\Client as WhisperClientContract;
use App\Platforms\Contracts\PlatformFactory as PlatformFactoryContract;
use App\Platforms\PlatformFactory;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
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

        $this->app->bind(PlatformFactoryContract::class, PlatformFactory::class);
        $this->app->bind(WhisperClientContract::class, WhisperClient::class);
        $this->app->bind(FfmpegClientContract::class, FfmpegClient::class);

        $this->app->make(BroadcastManager::class)->routes();
    }
}
