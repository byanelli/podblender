<?php

use Illuminate\Support\Facades\Route;

Route::get('/feeds/{feed}', \App\Http\Controllers\ShowFeedController::class)->name('showFeed');
