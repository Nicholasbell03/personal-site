<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

require __DIR__.'/auth.php';
