<?php

use App\Http\Controllers;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/rss/{feed:uuid}', Controllers\ShowRss::class)->name('rss');
Route::get('/login', fn() => view('login'))->name('showLogin');
Route::post('/login', Controllers\AttemptLogin::class)->name('attemptLogin');

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/home', Controllers\Home::class)->name('home');
    Route::get('/logout', Controllers\Logout::class)->name('logout');
    Route::get('/feeds/{feed}', Controllers\ShowFeed::class)->name('showFeed');
    Route::get('/feeds/{feed}/confirm', Controllers\ConfirmAddClipToFeed::class)->name('confirmAddClipToFeed');
    Route::post('/feeds/{feed}/add', Controllers\AddClipToFeed::class)->name('addClipToFeed');
});
