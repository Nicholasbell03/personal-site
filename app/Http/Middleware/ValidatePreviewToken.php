<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Draft previews use per-item, expiring tokens ("{expires}.{hmac}") signed with PREVIEW_TOKEN,
 * so a preview link only unlocks one draft for a limited time instead of every draft forever.
 */
class ValidatePreviewToken
{
    public const TTL_SECONDS = 7 * 24 * 60 * 60;

    /**
     * A preview token for one item, e.g. issue('blogs', 'my-draft'). Null when PREVIEW_TOKEN isn't set.
     */
    public static function issue(string $type, string $slug, ?int $expiresAt = null): ?string
    {
        $secret = config('app.preview_token');

        if (! filled($secret)) {
            return null;
        }

        $expiresAt ??= now()->getTimestamp() + self::TTL_SECONDS;

        return $expiresAt.'.'.self::signature($secret, $type, $slug, $expiresAt);
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Route names look like "v1.blogs.preview", so the type is the second segment.
        $type = explode('.', (string) $request->route()?->getName())[1] ?? '';
        $slug = (string) $request->route('slug');

        if (! $this->isValid($request->header('X-Preview-Token'), $type, $slug)) {
            Log::warning('ValidatePreviewToken: rejected preview request', [
                'type' => $type,
                'slug' => $slug,
                'ip' => $request->ip(),
            ]);

            throw new AccessDeniedHttpException('Invalid preview token');
        }

        return $next($request);
    }

    private function isValid(?string $token, string $type, string $slug): bool
    {
        $secret = config('app.preview_token');

        if (! filled($secret) || ! is_string($token) || ! preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $parts)) {
            return false;
        }

        $expiresAt = (int) $parts[1];

        return $expiresAt > now()->getTimestamp()
            && hash_equals(self::signature($secret, $type, $slug, $expiresAt), $parts[2]);
    }

    private static function signature(string $secret, string $type, string $slug, int $expiresAt): string
    {
        return hash_hmac('sha256', "{$type}|{$slug}|{$expiresAt}", $secret);
    }
}
