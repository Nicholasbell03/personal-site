<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies Cloudflare Turnstile tokens server-side (https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isEnabled(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    /**
     * Fails closed: a missing token, a rejected token or an unreachable Cloudflare all return false.
     */
    public function verify(?string $token, ?string $ip): bool
    {
        if (! filled($token)) {
            Log::warning('TurnstileVerifier: missing token', ['ip' => $ip]);

            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable $e) {
            Log::error('TurnstileVerifier: verification request failed', [
                'ip' => $ip,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }

        if ($response->successful() && $response->json('success') === true) {
            return true;
        }

        Log::warning('TurnstileVerifier: token rejected', [
            'ip' => $ip,
            'status' => $response->status(),
            'error_codes' => $response->json('error-codes'),
        ]);

        return false;
    }
}
