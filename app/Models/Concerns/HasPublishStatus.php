<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;

trait HasPublishStatus
{
    public static function bootHasPublishStatus(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('status')) {
                if ($model->status === PublishStatus::Published && $model->published_at === null) {
                    $model->published_at = now();
                }

                if ($model->status === PublishStatus::Draft) {
                    $model->published_at = null;
                }
            }
        });
    }
}
