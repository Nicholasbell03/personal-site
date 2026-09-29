<?php

namespace App\Services;

use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use App\Support\PaginatedPayload;
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
     * One page of the index. The whole list is cached under one key and sliced per request,
     * so a save only has to forget one entry and every page stays in step.
     *
     * @return array<string, mixed>
     */
    public function paginated(int $page): array
    {
        $all = Cache::remember(Project::getApiCacheKey().'.index', self::CACHE_TTL, function () {
            $projects = Project::query()
                ->published()
                ->with('technologies')
                ->latestPublished()
                ->get();

            return ProjectSummaryResource::collection($projects)->response()->getData(true)['data'];
        });

        return PaginatedPayload::make($all, $page);
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
            $project = $this->findPublished($slug)->load('technologies');

            return (new ProjectResource($project))->response()->getData(true);
        });
    }

    /**
     * @return array{data: array{next: array<string, mixed>|null, related: list<array<string, mixed>>}}
     */
    public function related(string $slug): array
    {
        return Cache::remember(Project::getApiCacheKey().".related.{$slug}", self::CACHE_TTL, function () use ($slug) {
            $project = $this->findPublished($slug);

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

    private function findPublished(string $slug): Project
    {
        return Project::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
