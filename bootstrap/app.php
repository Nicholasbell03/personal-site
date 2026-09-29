<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

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
        // Trust Render.com's proxy so Laravel detects HTTPS correctly
        $middleware->trustProxies(at: '*');

        // There is no public login page (Filament has its own), so guests get a 401 instead of a redirect.
        $middleware->redirectGuestsTo(null);

        $middleware->alias([
            'browser' => \App\Http\Middleware\ValidateBrowserRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
