<?php

namespace App\Services;

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

    private const RESULTS_PER_TYPE = 3;

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
     * Get semantically related items across all content types using pgvector.
     *
     * @param  Blog|Project|Share  $item
     * @return Collection<int, array{type: string, item: Blog|Project|Share}>
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

        $typeMap = [
            Blog::class => 'blog',
            Project::class => 'project',
            Share::class => 'share',
        ];

        foreach ($typeMap as $modelClass => $type) {
            $query = $modelClass::query()
                ->selectRaw('*, (1 - (embedding <=> ?)) as similarity', [$this->formatEmbedding($embedding)])
                ->whereNotNull('embedding');

            // Exclude current item if same type
            if ($item::class === $modelClass) {
                $query->where('id', '!=', $item->id);
            }

            if (method_exists($modelClass, 'scopePublished')) {
                $query->published();
            }

            try {
                $results = $query
                    ->whereRaw('(1 - (embedding <=> ?)) >= ?', [$this->formatEmbedding($embedding), self::MIN_SIMILARITY])
                    ->orderByDesc('similarity')
                    ->limit(self::RESULTS_PER_TYPE)
                    ->get();

                foreach ($results as $result) {
                    /** @var float $similarity */
                    $similarity = $result->getAttribute('similarity');
                    $candidates->push([
                        'type' => $type,
                        'item' => $result,
                        'similarity' => $similarity,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('RelatedContentService: vector search failed', [
                    'model' => $modelClass,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        /** @var Collection<int, array{type: string, item: Blog|Project|Share}> */
        return $candidates
            ->sortByDesc('similarity')
            ->take($limit)
            ->map(fn (array $candidate): array => [
                'type' => (string) $candidate['type'],
                'item' => $candidate['item'],
            ])
            ->values();
    }

    /**
     * Format an embedding array as a pgvector string.
     *
     * @param  list<float>  $embedding
     */
    private function formatEmbedding(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    private function isDefaultConnectionPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
