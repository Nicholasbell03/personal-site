<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Guards the endpoints cronjob.org calls. Requires the X-Cron-Secret header to match CRON_SECRET,
 * and refuses every request when CRON_SECRET isn't configured.
 */
class RequireCronSecret
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.cron_secret');
        $provided = $request->header('X-Cron-Secret');

        if (! filled($secret) || ! is_string($provided) || ! hash_equals($secret, $provided)) {
            Log::warning('RequireCronSecret: rejected cron request', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'secret_configured' => filled($secret),
            ]);

            throw new AccessDeniedHttpException('Invalid cron secret');
        }

        return $next($request);
    }
}
