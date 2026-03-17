<?php

namespace App\Models\Concerns;

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use Illuminate\Support\Facades\Log;

trait HasSummary
{
    public static function bootHasSummary(): void
    {
        static::created(function (self $model) {
            $commentary = trim(strip_tags((string) $model->commentary));

            if ($commentary === '') {
                return;
            }

            try {
                GenerateSummaryJob::dispatch($model);
            } catch (\Throwable $e) {
                Log::error('GenerateSummaryJob failed on creation', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
                if (property_exists($model, 'creationWarnings')) { // @phpstan-ignore function.alreadyNarrowedType
                    $model->creationWarnings[] = 'Summary generation failed.';
                }
            }

            try {
                PostToXJob::dispatch($model)->delay(now()->addMinutes(2));
            } catch (\Throwable $e) {
                Log::error('PostToXJob failed on creation', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
                if (property_exists($model, 'creationWarnings')) { // @phpstan-ignore function.alreadyNarrowedType
                    $model->creationWarnings[] = 'Post to X failed.';
                }
            }
        });
    }
}
