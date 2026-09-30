<?php

namespace App\Services;

use App\Http\Resources\RelatedItemResource;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RelatedContentService
{
    private const MIN_SIMILARITY = 0.3;

    /**
     * The `{data: {next, related}}` payload returned by the related-content endpoints.
     *
     * @param  Blog|Project|Share  $item
     * @return array{data: array{next: array<string, mixed>|null, related: list<array<string, mixed>>}}
     */
    public function payloadFor(Model $item): array
    {
        $next = $this->getNextItem($item);

        return [
            'data' => [
                'next' => $next ? (new RelatedItemResource($next))->resolve() : null,
                'related' => $this->getRelatedItems($item)
                    ->map(fn (array $related) => (new RelatedItemResource($related['item']))->resolve())
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Get the next chronologically published item of the same type.
     *
     * @param  Blog|Project|Share  $item
     * @return (Blog|Project|Share)|null
     */
    public function getNextItem(Model $item): ?Model
    {
        $dateColumn = $item instanceof Share ? 'created_at' : 'published_at';

        $query = $item->newQuery()
            ->where(function ($q) use ($item, $dateColumn) {
                $q->where($dateColumn, '>', $item->{$dateColumn})
                    ->orWhere(function ($q2) use ($item, $dateColumn) {
                        $q2->where($dateColumn, '=', $item->{$dateColumn})
                            ->where('id', '>', $item->id);
                    });
            })
            ->orderBy($dateColumn)
            ->orderBy('id');

        if (method_exists($item, 'scopePublished')) {
            $query->published();
        }

        return $query->first();
    }

    /**
     * Get the most similar items across all content types using pgvector, ranked by similarity with no
     * preference for any type. Each type contributes its closest $limit candidates, which always contain
     * the overall top $limit.
     *
     * @param  Blog|Project|Share  $item
     * @return Collection<int, array{item: Blog|Project|Share, distance: float}>
     */
    public function getRelatedItems(Model $item, int $limit = 3): Collection
    {
        if (! $this->isDefaultConnectionPostgres()) {
            return collect();
        }

        $embedding = $item->embedding;

        if ($embedding === null) {
            return collect();
        }

        $candidates = collect();

        $modelClasses = [Blog::class, Project::class, Share::class];

        foreach ($modelClasses as $modelClass) {
            $query = $modelClass::query()
                ->select('*')
                ->selectVectorDistance('embedding', $embedding, as: 'embedding_distance')
                ->whereNotNull('embedding')
                ->whereVectorSimilarTo('embedding', $embedding, self::MIN_SIMILARITY);

            if ($item::class === $modelClass) {
                $query->where('id', '!=', $item->id);
            }

            if (method_exists($modelClass, 'scopePublished')) {
                $query->published();
            }

            try {
                $results = $query->limit($limit)->get();

                foreach ($results as $result) {
                    $candidates->push(['item' => $result, 'distance' => (float) $result->getAttribute('embedding_distance')]);
                }
            } catch (\Throwable $e) {
                Log::error('RelatedContentService: vector search failed', [
                    'model' => $modelClass,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $candidates->sortBy('distance')->take($limit)->values();
    }

    private function isDefaultConnectionPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
