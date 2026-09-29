<?php

namespace App\Services;

use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * Reads behind the public project endpoints. The cached methods return the JSON payload, stored for 24 hours
 * under keys that Project::clearApiCache() forgets on save.
 */
class ProjectService
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
        return Cache::remember(Project::getApiCacheKey().".index.{$page}", self::CACHE_TTL, function () use ($page) {
            $projects = Project::query()
                ->published()
                ->with('technologies')
                ->latestPublished()
                ->paginate(10, page: $page)
                ->withPath(rtrim(config('app.url'), '/').route('v1.projects.index', absolute: false));

            return ProjectSummaryResource::collection($projects)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function featured(): array
    {
        return Cache::remember(Project::getApiCacheKey().'.featured', self::CACHE_TTL, function () {
            $projects = Project::query()
                ->published()
                ->with('technologies')
                ->featured()
                ->latestPublished()
                ->limit(3)
                ->get();

            return ProjectSummaryResource::collection($projects)->response()->getData(true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(string $slug): array
    {
        return Cache::remember(Project::getApiCacheKey().".show.{$slug}", self::CACHE_TTL, function () use ($slug) {
            $project = Project::query()
                ->published()
                ->where('slug', $slug)
                ->with('technologies')
                ->firstOrFail();

            return (new ProjectResource($project))->response()->getData(true);
        });
    }

    /**
     * @return array{data: array{next: array<string, mixed>|null, related: list<array<string, mixed>>}}
     */
    public function related(string $slug): array
    {
        return Cache::remember(Project::getApiCacheKey().".related.{$slug}", self::CACHE_TTL, function () use ($slug) {
            $project = Project::query()
                ->published()
                ->where('slug', $slug)
                ->firstOrFail();

            return $this->relatedContent->payloadFor($project);
        });
    }

    /**
     * Find a project by slug whatever its status, for token-protected previews. Never cached.
     */
    public function findForPreview(string $slug): Project
    {
        return Project::query()
            ->where('slug', $slug)
            ->with('technologies')
            ->firstOrFail();
    }
}
