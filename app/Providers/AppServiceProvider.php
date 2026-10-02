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
            Limit::perMinute(10)->by(self::clientNetwork($request)),
            Limit::perHour(50)->by(self::clientNetwork($request)),
            Limit::perDay(100)->by(self::clientNetwork($request)),
            // Shared by every visitor: rotating addresses can't push the LLM bill past this.
            Limit::perDay(config('agent.portfolio.daily_global_limit'))->by('global'),
        ]);

        RateLimiter::for('search', fn (Request $request) => [
            Limit::perMinute(60)->by(self::clientNetwork($request)),
            Limit::perDay(1000)->by(self::clientNetwork($request)),
        ]);

        LogViewer::auth(fn ($request): bool => (bool) $request->user()?->isAdmin());
    }

    /**
     * The rate-limit key for a client: its IPv4 address, or its IPv6 /64. One host usually
     * controls a whole /64, so per-address IPv6 limits are trivially bypassed.
     */
    public static function clientNetwork(Request $request): string
    {
        $ip = (string) $request->ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        return inet_ntop(substr((string) inet_pton($ip), 0, 8).str_repeat(chr(0), 8)).'/64';
    }
}
