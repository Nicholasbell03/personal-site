<?php

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ContributionActivity;
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

    public function activity(GitHubService $gitHubService): JsonResponse
    {
        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($gitHubService): ContributionActivity {
            $result = $gitHubService->fetchContributionActivity();

            if ($result !== null) {
                Cache::put(self::STALE_CACHE_KEY, $result, self::STALE_CACHE_TTL);

                return $result;
            }

            /** @var ContributionActivity */
            return Cache::get(self::STALE_CACHE_KEY, ContributionActivity::empty());
        });

        return response()->json(['data' => $data]);
    }
}
