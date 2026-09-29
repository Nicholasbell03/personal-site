<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;

class GitHubController extends Controller
{
    public function activity(GitHubService $gitHubService): JsonResponse
    {
        return response()->json(['data' => $gitHubService->contributionActivity()]);
    }
}
