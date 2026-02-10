<?php

namespace App\Models\Concerns;

use App\Jobs\GenerateEmbeddingJob;

trait HasEmbedding
{
    public static function bootHasEmbedding(): void
    {
        static::created(function (self $model) {
            GenerateEmbeddingJob::dispatch($model);
        });

        static::updated(function (self $model) {
            if ($model->wasChanged($model->getEmbeddableFields())) {
                GenerateEmbeddingJob::dispatch($model);
            }
        });
    }

    /**
     * Get the text that should be embedded for this model.
     */
    abstract public function getEmbeddableText(): string;

    /**
     * Get the fields that should trigger embedding regeneration when changed.
     *
     * @return list<string>
     */
    abstract public function getEmbeddableFields(): array;
}
