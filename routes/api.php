<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/health', function () {
    DB::select('SELECT 1');

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/warm-cache', function () {
    Artisan::call('api:warm-cache');

    return response()->json([
        'status' => 'ok',
        'message' => 'Cache warmed',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/warm-frontend', function () {
    $exitCode = Artisan::call('frontend:warm-pages');

    if ($exitCode !== 0) {
        return response()->json([
            'status' => 'error',
            'message' => 'Frontend page warming failed',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }

    return response()->json([
        'status' => 'ok',
        'message' => trim(Artisan::output()),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/check-linkedin-token', function () {
    $exitCode = Artisan::call('linkedin:check-token');

    if ($exitCode !== 0) {
        return response()->json([
            'status' => 'error',
            'message' => 'LinkedIn token check failed',
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }

    return response()->json([
        'status' => 'ok',
        'message' => 'LinkedIn token is valid',
        'timestamp' => now()->toIso8601String(),
    ]);
});
