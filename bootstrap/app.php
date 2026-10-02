<?php

use App\Http\Middleware\RequireCronSecret;
use App\Http\Middleware\ValidateBrowserRequest;
use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api/v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Render's proxy for the client IP and HTTPS only. Forwarded Host/Port stay untrusted,
        // so a spoofed X-Forwarded-Host can't poison generated URLs or redirects.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        // Reject requests for any other Host (skipped automatically in local and tests).
        $middleware->trustHosts(at: ['^api\.nickbell\.dev$', '^nickbell-dev\.onrender\.com$'], subdomains: false);

        // There is no public login page (Filament has its own), so guests get a 401 instead of a redirect.
        // The one exception: browsers opening the API docs are sent to the Filament login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('docs/*') ? route('filament.admin.auth.login') : null);

        $middleware->alias([
            'browser' => ValidateBrowserRequest::class,
            'abilities' => CheckAbilities::class,
            'cron' => RequireCronSecret::class,
            'turnstile' => VerifyTurnstile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
