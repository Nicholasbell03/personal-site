<?php

namespace App\Models\Concerns;

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;

trait HasSummary
{
    public static function bootHasSummary(): void
    {
        static::created(function (self $model) {
            $commentary = trim(strip_tags((string) $model->commentary));

            if ($commentary === '') {
                return;
            }

            GenerateSummaryJob::dispatch($model);
            PostToXJob::dispatch($model)->delay(now()->addMinutes(2));
        });
    }
}
