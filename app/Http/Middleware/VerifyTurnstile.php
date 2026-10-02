<?php

namespace App\Http\Middleware;

use App\Http\Responses\SseErrorResponse;
use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid Cloudflare Turnstile token (X-Turnstile-Token) once TURNSTILE_SECRET_KEY is set,
 * so scripts can't drive the paid chat agent. Without a secret (local dev) it lets requests through.
 */
class VerifyTurnstile
{
    public function __construct(private TurnstileVerifier $turnstile) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->turnstile->isEnabled()) {
            return $next($request);
        }

        if (! $this->turnstile->verify($request->header('X-Turnstile-Token'), $request->ip())) {
            return SseErrorResponse::make(
                'We couldn\'t verify you\'re human. Please refresh the page and try again.',
                'verification_failed',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
