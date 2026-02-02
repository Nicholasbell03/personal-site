<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ValidatePreviewToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Preview-Token');
        $validToken = config('app.preview_token');

        if (! $token || ! $validToken || ! hash_equals($validToken, $token)) {
            throw new AccessDeniedHttpException('Invalid preview token');
        }

        return $next($request);
    }
}
