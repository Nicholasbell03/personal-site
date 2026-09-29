<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/feed', \App\Http\Controllers\FeedController::class)->name('feed.rss');
