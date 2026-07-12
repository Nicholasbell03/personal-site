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

    private const FAILURE_RETRY_TTL = 60 * 5; // 5 minutes

    private const CACHE_KEY = 'api.v1.github.activity';

    private const STALE_CACHE_KEY = 'api.v1.github.activity.stale';

    public function activity(GitHubService $gitHubService): JsonResponse
    {
        /** @var ContributionActivity|null $data */
        $data = Cache::get(self::CACHE_KEY);

        if ($data === null) {
            $result = $gitHubService->fetchContributionActivity();

            if ($result !== null) {
                Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);
                Cache::put(self::STALE_CACHE_KEY, $result, self::STALE_CACHE_TTL);

                $data = $result;
            } else {
                // Live fetch failed: serve the stale snapshot but only cache it
                // briefly so the next request retries GitHub soon, instead of
                // pinning stale data under the fresh key for a full 24 hours.
                /** @var ContributionActivity $data */
                $data = Cache::get(self::STALE_CACHE_KEY, ContributionActivity::empty());

                Cache::put(self::CACHE_KEY, $data, self::FAILURE_RETRY_TTL);
            }
        }

        return response()->json(['data' => $data]);
    }
}
