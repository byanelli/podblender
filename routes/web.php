<?php

use App\Http\Controllers;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/rss/{feed:uuid}', Controllers\ShowRss::class)->name('rss');

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/', Controllers\Welcome::class)->name('welcome');

    Route::get('/dashboard', Controllers\Home::class)->name('dashboard');

    Route::get('/feeds/{feed}', Controllers\ShowFeed::class)->name('showFeed');

    Route::prefix('api')->group(function () {
        // todo next
        Route::post('/fetch-metadata', Controllers\FetchMetadata::class)->name('fetchMetadata');
        Route::post('/feeds/{feed}/add', Controllers\AddClipToFeed::class)->name('addClipToFeed');
    });
});

require __DIR__.'/auth.php';
