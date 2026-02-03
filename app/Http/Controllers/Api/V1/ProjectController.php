<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProjectController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function index(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $cacheKey = Project::getApiCacheKey().".index.{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $projects = Project::query()
                ->published()
                ->with('technologies')
                ->latestPublished()
                ->paginate(10);

            return ProjectSummaryResource::collection($projects)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function featured(): JsonResponse
    {
        $cacheKey = Project::getApiCacheKey().'.featured';

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $projects = Project::query()
                ->published()
                ->with('technologies')
                ->featured()
                ->latestPublished()
                ->limit(3)
                ->get();

            return ProjectSummaryResource::collection($projects)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function show(string $slug): JsonResponse
    {
        $cacheKey = Project::getApiCacheKey().".show.{$slug}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug) {
            $project = Project::query()
                ->published()
                ->where('slug', $slug)
                ->with('technologies')
                ->firstOrFail();

            return (new ProjectResource($project))->response()->getData(true);
        });

        return response()->json($data);
    }

    public function preview(string $slug): ProjectResource
    {
        $project = Project::query()
            ->where('slug', $slug)
            ->with('technologies')
            ->firstOrFail();

        return new ProjectResource($project);
    }
}
