<?php

namespace App\Services;

use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use App\Support\PaginatedPayload;
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
     * One page of the index. The whole list is cached under one key and sliced per request,
     * so a save only has to forget one entry and every page stays in step.
     *
     * @return array<string, mixed>
     */
    public function paginated(int $page): array
    {
        $all = Cache::remember(Blog::getApiCacheKey().'.index', self::CACHE_TTL, function () {
            $blogs = Blog::query()
                ->published()
                ->latestPublished()
                ->get();

            return BlogSummaryResource::collection($blogs)->response()->getData(true)['data'];
        });

        return PaginatedPayload::make($all, $page);
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
