<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ValidateBrowserRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);

        if (! $origin || (! in_array('*', $allowedOrigins) && ! in_array($origin, $allowedOrigins))) {
            Log::warning('ValidateBrowserRequest: invalid Origin header', [
                'ip' => $request->ip(),
                'origin' => $origin,
            ]);

            throw new AccessDeniedHttpException('Forbidden.');
        }

        $secFetchSite = $request->header('Sec-Fetch-Site');
        $secFetchMode = $request->header('Sec-Fetch-Mode');

        if (! $secFetchSite || $secFetchMode !== 'cors') {
            Log::warning('ValidateBrowserRequest: invalid Sec-Fetch headers', [
                'ip' => $request->ip(),
                'sec_fetch_site' => $secFetchSite,
                'sec_fetch_mode' => $secFetchMode,
            ]);

            throw new AccessDeniedHttpException('Forbidden.');
        }

        return $next($request);
    }
}
