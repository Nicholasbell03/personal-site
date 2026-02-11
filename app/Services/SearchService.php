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
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';
        $queryVector = $isPostgres ? $this->embedQuery($query) : null;

        $tasks = [];

        if ($type === 'all' || $type === 'blog') {
            $tasks['blogs'] = fn () => $this->searchBlogs($query, $queryVector);
        }

        if ($type === 'all' || $type === 'project') {
            $tasks['projects'] = fn () => $this->searchProjects($query, $queryVector);
        }

        if ($type === 'all' || $type === 'share') {
            $tasks['shares'] = fn () => $this->searchShares($query, $queryVector);
        }

        if ($isPostgres && count($tasks) > 1) {
            try {
                return Concurrency::run($tasks);
            } catch (\Throwable $e) {
                Log::warning('SearchService: concurrent search failed, falling back to sequential', [
                    'query' => $query,
                    'exception' => $e,
                ]);
            }
        }

        return array_map(fn ($task) => $task(), $tasks);
    }

    /**
     * Embed the search query text once upfront.
     *
     * @return list<float>|null
     */
    private function embedQuery(string $query): ?array
    {
        try {
            return Str::of($query)->toEmbeddings(cache: true);
        } catch (\Throwable $e) {
            Log::error('SearchService: query embedding failed', [
                'query' => $query,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * @param  list<float>|null  $queryVector
     * @return list<array<string, mixed>>
     */
    private function searchBlogs(string $query, ?array $queryVector): array
    {
        $builder = Blog::query()->published();
        $fields = ['title', 'excerpt', 'content'];

        $blogs = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields, $queryVector)
            : $this->likeSearch($builder, $query, $fields);

        return BlogSummaryResource::collection($blogs)->resolve();
    }

    /**
     * @param  list<float>|null  $queryVector
     * @return list<array<string, mixed>>
     */
    private function searchProjects(string $query, ?array $queryVector): array
    {
        $builder = Project::query()->published()->with('technologies');
        $fields = ['title', 'description', 'long_description'];

        $projects = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields, $queryVector)
            : $this->likeSearch($builder, $query, $fields);

        return ProjectSummaryResource::collection($projects)->resolve();
    }

    /**
     * @param  list<float>|null  $queryVector
     * @return list<array<string, mixed>>
     */
    private function searchShares(string $query, ?array $queryVector): array
    {
        $builder = Share::query();
        $fields = ['title', 'description', 'commentary', 'url'];

        $shares = $this->isPostgres($builder)
            ? $this->hybridSearch($builder, $query, $fields, $queryVector)
            : $this->likeSearch($builder, $query, $fields);

        return ShareSummaryResource::collection($shares)->resolve();
    }

    /**
     * Semantic search using pgvector cosine similarity.
     *
     * Accepts a pre-computed embedding vector and finds the nearest
     * neighbours by cosine distance. Only returns results that have
     * an embedding and meet the minimum similarity threshold.
     *
     * @param  Builder<*>  $queryBuilder
     * @param  list<float>  $queryVector
     */
    private function vectorSearch(Builder $queryBuilder, array $queryVector): Collection
    {
        try {
            return $queryBuilder
                ->whereNotNull('embedding')
                ->whereVectorSimilarTo('embedding', $queryVector, self::MIN_SIMILARITY)
                ->limit(self::RESULTS_PER_TYPE)
                ->get();
        } catch (\Throwable $e) {
            Log::error('SearchService: vector search failed', [
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
     */
    private function keywordSearch(Builder $queryBuilder, string $query, array $fields): Collection
    {
        $terms = collect(preg_split('/\s+/', trim($query)))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        return $queryBuilder
            ->where(function ($q) use ($terms, $fields) {
                foreach ($terms as $term) {
                    $q->where(function ($inner) use ($term, $fields) {
                        foreach ($fields as $field) {
                            $inner->orWhere($field, 'ILIKE', "%{$term}%");
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
     * @param  list<float>|null  $queryVector
     */
    private function hybridSearch(Builder $queryBuilder, string $query, array $fields, ?array $queryVector): Collection
    {
        $vectorResults = $queryVector
            ? $this->vectorSearch(clone $queryBuilder, $queryVector)
            : collect();
        $keywordResults = $this->keywordSearch(clone $queryBuilder, $query, $fields);

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
     */
    private function likeSearch(Builder $queryBuilder, string $query, array $fields): Collection
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

    /**
     * @param  Builder<*>  $queryBuilder
     */
    private function isPostgres(Builder $queryBuilder): bool
    {
        $connection = $queryBuilder->getConnection();

        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql';
    }
}
