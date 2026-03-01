<?php

namespace App\Models\Concerns;

use App\Jobs\ProcessShareSummaryAndTweetJob;

trait HasSummary
{
    public static function bootHasSummary(): void
    {
        static::created(function (self $model) {
            ProcessShareSummaryAndTweetJob::dispatch($model);
        });
    }
}
