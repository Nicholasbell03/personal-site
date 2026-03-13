<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use App\Jobs\PostContentToXJob;
use App\Jobs\PostToLinkedInJob;
use Illuminate\Support\Facades\Storage;

trait HasDownstreamPosting
{
    public function getDownstreamImageUrl(): ?string
    {
        if (! $this->featured_image) {
            return null;
        }

        return Storage::url($this->featured_image);
    }

    public static function bootHasDownstreamPosting(): void
    {
        static::saved(function ($model) {
            if (! $model->wasChanged('status') || $model->status !== PublishStatus::Published) {
                return;
            }

            if ($model->x_post_id === null && $model->post_to_x) {
                PostContentToXJob::dispatch($model)->delay(now()->addMinutes(2));
            }

            if ($model->linkedin_post_id === null && $model->post_to_linkedin) {
                PostToLinkedInJob::dispatch($model)->delay(now()->addMinutes(2));
            }
        });
    }
}
