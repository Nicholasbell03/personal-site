<?php

namespace App\Services;

use App\Http\Resources\ShareResource;
use App\Http\Resources\ShareSummaryResource;
use App\Models\Share;
use Illuminate\Support\Facades\Cache;

/**
 * Reads behind the public share endpoints. Each method returns the JSON payload, stored for 24 hours
 * under keys that Share::clearApiCache() forgets on save. Shares have no draft state.
 */
class ShareService
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
        return Cache::remember(Share::getApiCacheKey().".index.{$page}", self::CACHE_TTL, function () use ($page) {
            $shares = Share::query()
                ->latest()
                ->paginate(10, page: $page)
                ->withPath(rtrim(config('app.url'), '/').route('v1.shares.index', absolute: false));

            return ShareSummaryResource::collection($shares)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function featured(): array
    {
        return Cache::remember(Share::getApiCacheKey().'.featured', self::CACHE_TTL, function () {
            $shares = Share::query()
                ->latest()
                ->limit(3)
                ->get();

            return ShareSummaryResource::collection($shares)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $slug): array
    {
        return Cache::remember(Share::getApiCacheKey().".show.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return (new ShareResource($this->findBySlug($slug)))->response()->getData(true);
        });
    }

    /**
     * @return array{data: array{next: array<string, mixed>|null, related: list<array<string, mixed>>}}
     */
    public function related(string $slug): array
    {
        return Cache::remember(Share::getApiCacheKey().".related.{$slug}", self::CACHE_TTL, function () use ($slug) {
            return $this->relatedContent->payloadFor($this->findBySlug($slug));
        });
    }

    private function findBySlug(string $slug): Share
    {
        return Share::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
