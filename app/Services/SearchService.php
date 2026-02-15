<?php

namespace App\Services;

use App\Http\Resources\BlogSummaryResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Resources\ShareSummaryResource;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
        $builder = Blog::query()->published();
        $fields = ['title', 'excerpt', 'content'];

        $blogs = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields)
            : $this->likeSearch($builder, $query, $fields);

        return BlogSummaryResource::collection($blogs)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchProjects(string $query): array
    {
        $builder = Project::query()->published()->with('technologies');
        $fields = ['title', 'description', 'long_description'];

        $technologyMatcher = function (Builder $inner, string $term): void {
            $inner->orWhereHas('technologies', fn ($q) => $q->where('name', 'LIKE', "%{$term}%"));
        };

        $projects = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields, $technologyMatcher)
            : $this->likeSearch($builder, $query, $fields, $technologyMatcher);

        return ProjectSummaryResource::collection($projects)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchShares(string $query): array
    {
        $builder = Share::query();
        $fields = ['title', 'description', 'commentary', 'url'];

        $shares = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields)
            : $this->likeSearch($builder, $query, $fields);

        return ShareSummaryResource::collection($shares)->resolve();
    }

    /**
     * Semantic search using pgvector cosine similarity.
     *
     * Embeds the query text via the configured AI provider, then finds
     * the nearest neighbours by cosine distance. Only returns results
     * that have an embedding and meet the minimum similarity threshold.
     *
     * @param  Builder<*>  $queryBuilder
     */
    private function vectorSearch(Builder $queryBuilder, string $query): Collection
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
                'exception' => $e,
            ]);

            return collect();
        }
    }

    /**
     * Keyword search using ILIKE on PostgreSQL.
     *
     * Uses AND semantics — all terms must match at least one field.
     *
     * @param  Builder<*>  $queryBuilder
     * @param  list<string>  $fields
     * @param  (\Closure(Builder<*>, string): void)|null  $additionalTermMatcher
     */
    private function keywordSearch(Builder $queryBuilder, string $query, array $fields, ?\Closure $additionalTermMatcher = null): Collection
    {
        $terms = collect(preg_split('/\s+/', trim($query)))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        return $queryBuilder
            ->where(function ($q) use ($terms, $fields, $additionalTermMatcher) {
                foreach ($terms as $term) {
                    $q->where(function ($inner) use ($term, $fields, $additionalTermMatcher) {
                        foreach ($fields as $field) {
                            $inner->orWhere($field, 'ILIKE', "%{$term}%");
                        }
                        if ($additionalTermMatcher) {
                            $additionalTermMatcher($inner, $term);
                        }
                    });
                }
            })
            ->limit(self::RESULTS_PER_TYPE)
            ->get();
    }

    /**
     * Hybrid search combining vector similarity and keyword matching via Reciprocal Rank Fusion.
     *
     * Documents appearing in both result sets get boosted scores.
     *
     * @param  Builder<*>  $queryBuilder
     * @param  list<string>  $fields
     * @param  (\Closure(Builder<*>, string): void)|null  $additionalTermMatcher
     */
    private function hybridSearch(Builder $queryBuilder, string $query, array $fields, ?\Closure $additionalTermMatcher = null): Collection
    {
        $vectorResults = $this->vectorSearch(clone $queryBuilder, $query);
        $keywordResults = $this->keywordSearch(clone $queryBuilder, $query, $fields, $additionalTermMatcher);

        $k = 60;
        $scores = [];
        /** @var array<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = [];

        foreach ($vectorResults->values() as $i => $model) {
            $scores[$model->id] = ($scores[$model->id] ?? 0) + (1 / ($k + $i + 1));
            $models[$model->id] = $model;
        }

        foreach ($keywordResults->values() as $i => $model) {
            $scores[$model->id] = ($scores[$model->id] ?? 0) + (1 / ($k + $i + 1));
            $models[$model->id] = $model;
        }

        arsort($scores);

        return collect(array_keys($scores))
            ->take(self::RESULTS_PER_TYPE)
            ->map(fn ($id) => $models[$id])
            ->values();
    }

    /**
     * Fallback LIKE-based search for SQLite (tests).
     *
     * Uses AND semantics between terms — all terms must match at least one field.
     *
     * @param  Builder<*>  $queryBuilder
     * @param  list<string>  $fields
     * @param  (\Closure(Builder<*>, string): void)|null  $additionalTermMatcher
     */
    private function likeSearch(Builder $queryBuilder, string $query, array $fields, ?\Closure $additionalTermMatcher = null): Collection
    {
        $terms = collect(preg_split('/\s+/', trim($query)))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return $queryBuilder->limit(0)->get();
        }

        return $queryBuilder
            ->where(function ($q) use ($terms, $fields, $additionalTermMatcher) {
                foreach ($terms as $term) {
                    $q->where(function ($inner) use ($term, $fields, $additionalTermMatcher) {
                        foreach ($fields as $field) {
                            $inner->orWhere($field, 'LIKE', "%{$term}%");
                        }
                        if ($additionalTermMatcher) {
                            $additionalTermMatcher($inner, $term);
                        }
                    });
                }
            })
            ->limit(self::RESULTS_PER_TYPE)
            ->get();
    }

    /**
     * @param  Builder<*>  $queryBuilder
     */
    private function isPostgres(Builder $queryBuilder): bool
    {
        $connection = $queryBuilder->getConnection();

        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql';
    }
}
