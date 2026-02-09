<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GitHubController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    private const STALE_CACHE_TTL = 60 * 60 * 24 * 7; // 7 days

    private const CACHE_KEY = 'api.v1.github.activity';

    private const STALE_CACHE_KEY = 'api.v1.github.activity.stale';

    /** @var array{daily_contributions: list<mixed>, stats: array{total_last_30_days: int, total_last_90_days: int, current_streak: int, longest_streak: int, average_per_day: float}} */
    private const EMPTY_RESPONSE = [
        'daily_contributions' => [],
        'stats' => [
            'total_last_30_days' => 0,
            'total_last_90_days' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'average_per_day' => 0.0,
        ],
    ];

    public function activity(GitHubService $gitHubService): JsonResponse
    {
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($gitHubService) {
            $result = $gitHubService->fetchContributionActivity();

            if ($result !== null) {
                Cache::put(self::STALE_CACHE_KEY, $result, self::STALE_CACHE_TTL);

                return $result;
            }

            // Try stale cache as fallback
            return Cache::get(self::STALE_CACHE_KEY, self::EMPTY_RESPONSE);
        });

        return response()->json(['data' => $data]);
    }
}
