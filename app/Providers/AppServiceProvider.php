<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

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
        RateLimiter::for('chat', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perHour(50)->by($request->ip()),
            Limit::perDay(100)->by($request->ip()),
        ]);

        RateLimiter::for('search', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
            Limit::perDay(1000)->by($request->ip()),
        ]);

        LogViewer::auth(function ($request) {
            return $request->user()?->email === config('log-viewer.admin_email');
        });
    }
}
