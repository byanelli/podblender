<?php

namespace App\Providers;

use App\Apis\Whisper\Client as WhisperClient;
use App\Apis\Whisper\Contracts\Client as WhisperClientContract;
use App\Platforms\PlatformFactory;
use App\Platforms\Contracts\PlatformFactory as PlatformFactoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
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
        Date::use(CarbonImmutable::class);

        $this->app->bind(PlatformFactoryContract::class, PlatformFactory::class);
        $this->app->bind(WhisperClientContract::class, WhisperClient::class);
    }
}
