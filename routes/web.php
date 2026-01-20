<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/phpinfo', function () {
    return phpinfo();
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'Basic route working',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
    ]);
});

require __DIR__.'/auth.php';
