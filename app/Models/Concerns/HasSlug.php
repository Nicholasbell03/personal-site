<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->{$model->getSlugSourceColumn()});
            }
        });
    }

    public function getSlugSourceColumn(): string
    {
        return $this->slugSource ?? 'title';
    }
}
