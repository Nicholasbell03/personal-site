<?php

namespace App\Services;

use App\Http\Resources\ShareResource;
use App\Http\Resources\ShareSummaryResource;
use App\Models\Share;
use App\Support\PaginatedPayload;
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
     * One page of the index. The whole list is cached under one key and sliced per request,
     * so a save only has to forget one entry and every page stays in step.
     *
     * @return array<string, mixed>
     */
    public function paginated(int $page): array
    {
        $all = Cache::remember(Share::getApiCacheKey().'.index', self::CACHE_TTL, function () {
            $shares = Share::query()
                ->latest()
                ->get();

            return ShareSummaryResource::collection($shares)->response()->getData(true)['data'];
        });

        return PaginatedPayload::make($all, $page);
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
