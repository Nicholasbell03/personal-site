<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

class EmbeddingService
{
    /**
     * Generate and store an embedding for the given model.
     *
     * @param  Blog|Project|Share  $model
     */
    public function generateFor(Model $model): bool
    {
        /** @var Blog|Project|Share $model */
        $text = trim($model->getEmbeddableText());

        if ($text === '') {
            Log::warning('EmbeddingService: empty embeddable text, skipping', [
                'model' => $model::class,
                'id' => $model->getKey(),
            ]);

            return false;
        }

        $provider = config('services.embeddings.provider');
        $embeddingModel = config('services.embeddings.model');
        $dimensions = config('services.embeddings.dimensions');

        try {
            $response = Embeddings::for([$text])
                ->dimensions($dimensions)
                ->generate($provider, $embeddingModel);

            $embedding = $response->embeddings[0];

            $model->embedding = $embedding;
            $model->embedding_generated_at = now();
            $model->saveQuietly();

            Log::info('EmbeddingService: embedding generated', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'dimensions' => count($embedding),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('EmbeddingService: embedding generation failed', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
