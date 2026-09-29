<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBlogDraft;
use App\Actions\UpdateBlogDraft;
use App\Filament\Resources\Blogs\BlogResource as FilamentBlogResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBlogRequest;
use App\Http\Requests\Api\V1\UpdateBlogRequest;
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

    /**
     * Create a blog draft.
     *
     * Always creates a draft, whatever `status` is sent. Set `featured_image` to a `path` or `url`
     * returned by `POST /api/v1/media`, and embed image URLs directly in the `content` HTML.
     */
    public function store(StoreBlogRequest $request, CreateBlogDraft $createBlogDraft): JsonResponse
    {
        $blog = $createBlogDraft->execute($request->validated());

        return $this->draftResponse($blog, 201);
    }

    /**
     * Update a blog draft.
     *
     * Partial update: only the fields sent are changed, and `featured_image: null` removes the image.
     * Published blogs return 409, so a live post can never be changed through the API.
     */
    public function update(UpdateBlogRequest $request, Blog $blog, UpdateBlogDraft $updateBlogDraft): JsonResponse
    {
        $blog = $updateBlogDraft->execute($blog, $request->validated());

        return $this->draftResponse($blog, 200);
    }

    public function preview(string $slug): BlogResource
    {
        $blog = Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }

    private function draftResponse(Blog $blog, int $status): JsonResponse
    {
        return (new BlogResource($blog))
            ->additional(['admin_url' => FilamentBlogResource::getUrl('edit', ['record' => $blog])])
            ->response()
            ->setStatusCode($status);
    }
}
