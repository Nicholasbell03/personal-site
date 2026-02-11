<?php

namespace App\Services;

use App\Http\Resources\BlogSummaryResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Resources\ShareSummaryResource;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchService
{
    private const RESULTS_PER_TYPE = 5;

    private const MIN_SIMILARITY = 0.3;

    /**
     * Search across content types using semantic vector search.
     *
     * @param  'all'|'blog'|'project'|'share'  $type
     * @return array<string, list<array<string, mixed>>>
     */
    public function search(string $query, string $type = 'all'): array
    {
        $results = [];

        if ($type === 'all' || $type === 'blog') {
            $results['blogs'] = $this->searchBlogs($query);
        }

        if ($type === 'all' || $type === 'project') {
            $results['projects'] = $this->searchProjects($query);
        }

        if ($type === 'all' || $type === 'share') {
            $results['shares'] = $this->searchShares($query);
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchBlogs(string $query): array
    {
        $blogs = $this->isPostgres()
            ? $this->vectorSearch(Blog::query()->published(), $query)
            : $this->likeSearch(Blog::query()->published(), $query, ['title', 'excerpt', 'content']);

        return BlogSummaryResource::collection($blogs)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchProjects(string $query): array
    {
        $projects = $this->isPostgres()
            ? $this->vectorSearch(Project::query()->published()->with('technologies'), $query)
            : $this->likeSearch(Project::query()->published()->with('technologies'), $query, ['title', 'description', 'long_description']);

        return ProjectSummaryResource::collection($projects)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchShares(string $query): array
    {
        $shares = $this->isPostgres()
            ? $this->vectorSearch(Share::query(), $query)
            : $this->likeSearch(Share::query(), $query, ['title', 'description', 'commentary']);

        return ShareSummaryResource::collection($shares)->resolve();
    }

    /**
     * Semantic search using pgvector cosine similarity.
     *
     * Embeds the query text via the configured AI provider, then finds
     * the nearest neighbours by cosine distance. Only returns results
     * that have an embedding and meet the minimum similarity threshold.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $queryBuilder
     */
    private function vectorSearch(mixed $queryBuilder, string $query): mixed
    {
        try {
            return $queryBuilder
                ->whereNotNull('embedding')
                ->whereVectorSimilarTo('embedding', $query, self::MIN_SIMILARITY)
                ->limit(self::RESULTS_PER_TYPE)
                ->get();
        } catch (\Throwable $e) {
            Log::error('SearchService: vector search failed', [
                'query' => $query,
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Fallback LIKE-based search for SQLite (tests).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $queryBuilder
     * @param  list<string>  $fields
     */
    private function likeSearch(mixed $queryBuilder, string $query, array $fields): mixed
    {
        $terms = collect(preg_split('/\s+/', trim($query)))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return $queryBuilder->limit(0)->get();
        }

        return $queryBuilder
            ->where(function ($q) use ($terms, $fields) {
                foreach ($terms as $term) {
                    $q->where(function ($inner) use ($term, $fields) {
                        foreach ($fields as $field) {
                            $inner->orWhere($field, 'LIKE', "%{$term}%");
                        }
                    });
                }
            })
            ->limit(self::RESULTS_PER_TYPE)
            ->get();
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
