<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function index(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $cacheKey = Blog::getApiCacheKey().".index.{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $blogs = Blog::query()
                ->published()
                ->latestPublished()
                ->paginate(10);

            return BlogSummaryResource::collection($blogs)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function featured(): JsonResponse
    {
        $cacheKey = Blog::getApiCacheKey().'.featured';

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $blogs = Blog::query()
                ->published()
                ->latestPublished()
                ->limit(3)
                ->get();

            return BlogSummaryResource::collection($blogs)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function show(string $slug): JsonResponse
    {
        $cacheKey = Blog::getApiCacheKey().".show.{$slug}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug) {
            $blog = Blog::query()
                ->published()
                ->where('slug', $slug)
                ->firstOrFail();

            return (new BlogResource($blog))->response()->getData(true);
        });

        return response()->json($data);
    }

    public function preview(string $slug): BlogResource
    {
        $blog = Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }
}
