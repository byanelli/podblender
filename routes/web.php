<?php

use App\Http\Controllers;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/rss/{feed:uuid}', Controllers\ShowRss::class)->name('rss');

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/', Controllers\Home::class)->name('dashboard');

    Route::get('/feeds/{feed}', Controllers\ShowFeed::class)->name('showFeed');

    Route::delete('/feeds/{feed}/clips/{clip}', Controllers\DeleteClip::class)->name('deleteClip');
    Route::delete('/feeds/{feed}', Controllers\DeleteFeed::class)->name('deleteFeed');

    Route::post('/feeds/subscription', Controllers\CreateSubscription::class)->name('createSubscription');
    Route::post('/feeds', Controllers\CreateCustomFeed::class)->name('createCustomFeed');

    Route::prefix('api')->group(function () {
        // todo next
        Route::post('/fetch-metadata', Controllers\FetchMetadata::class)->name('fetchMetadata');
        Route::post('/fetch-source-metadata', Controllers\FetchSourceMetadata::class)->name('fetchSourceMetadata');
        Route::post('/feeds/{feed}/add', Controllers\AddClipToFeed::class)->name('addClipToFeed');
    });
});

require __DIR__.'/auth.php';

// Serve Vite's dev-server modules through this same app, so they stay
// same-origin and work through a tunnel like ngrok (public/hot only exists
// while "npm run dev" is running — the same gate @vite uses). The prefixes
// cover both Vite-internal modules (@vite, node_modules) and project source
// (resources/), including Ziggy, which is imported straight from vendor/.
if (is_file(public_path('hot'))) {
    Route::get('/{asset}', Controllers\ViteDevProxy::class)
        ->where('asset', '(?:resources/.*|vendor/.*|node_modules/.*|@vite/.*|@fs/.*|@react-refresh)')
        ->name('vite.dev.asset');
}
