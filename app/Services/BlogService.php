<?php

namespace App\Services;

use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use Illuminate\Support\Facades\Cache;

/**
 * Reads behind the public blog endpoints. The cached methods return the JSON payload, stored for 24 hours
 * under keys that Blog::clearApiCache() forgets on save.
 */
class BlogService
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __construct(private RelatedContentService $relatedContent) {}

    /**
     * Pagination links are built from APP_URL and the index route, never the current request. The
     * cache is warmed from `api:warm-cache` (the CLI or `GET /api/warm-cache`), and a forged Host or
     * X-Forwarded-Host on a cold cache would otherwise be served to every visitor for 24 hours.
     *
     * @return array<string, mixed>
     */
    public function paginated(int $page): array
    {
        return Cache::remember(Blog::getApiCacheKey().".index.{$page}", self::CACHE_TTL, function () use ($page) {
            $blogs = Blog::query()
                ->published()
                ->latestPublished()
                ->paginate(10, page: $page)
                ->withPath(rtrim(config('app.url'), '/').route('v1.blogs.index', absolute: false));

            return BlogSummaryResource::collection($blogs)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function featured(): array
    {
        return Cache::remember(Blog::getApiCacheKey().'.featured', self::CACHE_TTL, function () {
            $blogs = Blog::query()
                ->published()
                ->latestPublished()
                ->limit(3)
                ->get();

            return BlogSummaryResource::collection($blogs)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $slug): array
    {
        return Cache::remember(Blog::getApiCacheKey().".show.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return (new BlogResource($this->findPublished($slug)))->response()->getData(true);
        });
    }

    /**
     * @return array{data: array{next: array<string, mixed>|null, related: list<array<string, mixed>>}}
     */
    public function related(string $slug): array
    {
        return Cache::remember(Blog::getApiCacheKey().".related.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return $this->relatedContent->payloadFor($this->findPublished($slug));
        });
    }

    /**
     * Find a blog by slug whatever its status, for token-protected previews. Never cached.
     */
    public function findForPreview(string $slug): Blog
    {
        return Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function findPublished(string $slug): Blog
    {
        return Blog::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
