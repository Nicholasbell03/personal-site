<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Scope a query to only include published records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::Published)
            ->whereNotNull('published_at');
    }
}
