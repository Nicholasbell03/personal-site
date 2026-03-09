<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PublishStatus;
use App\Filament\Resources\Blogs\BlogResource as FilamentBlogResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBlogRequest;
use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Http\Resources\RelatedItemResource;
use App\Models\Blog;
use App\Services\RelatedContentService;
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

    public function related(string $slug, RelatedContentService $service): JsonResponse
    {
        $cacheKey = Blog::getApiCacheKey().".related.{$slug}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug, $service) {
            $blog = Blog::query()
                ->published()
                ->where('slug', $slug)
                ->firstOrFail();

            $next = $service->getNextItem($blog);
            $related = $service->getRelatedItems($blog);

            return [
                'data' => [
                    'next' => $next ? (new RelatedItemResource($next))->resolve() : null,
                    'related' => $related->map(fn (array $item) => (new RelatedItemResource($item['item']))->resolve())->values()->all(),
                ],
            ];
        });

        return response()->json($data);
    }

    public function store(StoreBlogRequest $request): JsonResponse
    {
        $blog = Blog::create([
            ...$request->validated(),
            'status' => PublishStatus::Draft,
        ]);

        return response()->json([
            'data' => (new BlogResource($blog))->resolve(),
            'admin_url' => FilamentBlogResource::getUrl('edit', ['record' => $blog]),
        ], 201);
    }

    public function preview(string $slug): BlogResource
    {
        $blog = Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }
}
