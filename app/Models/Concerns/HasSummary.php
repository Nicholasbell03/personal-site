<?php

namespace App\Models\Concerns;

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use Illuminate\Support\Facades\Bus;

trait HasSummary
{
    public static function bootHasSummary(): void
    {
        static::created(function (self $model) {
            $commentary = trim(strip_tags((string) $model->commentary));

            if ($commentary === '') {
                return;
            }

            Bus::chain([
                new GenerateSummaryJob($model),
                new PostToXJob($model),
            ])->dispatch();
        });
    }
}
