<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $slug = Str::slug($model->{$model->getSlugSourceColumn()});
                $model->slug = static::ensureUniqueSlug($slug);
            }
        });
    }

    public function getSlugSourceColumn(): string
    {
        return $this->slugSource ?? 'title';
    }

    protected static function ensureUniqueSlug(string $slug): string
    {
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.++$count;
        }

        return $slug;
    }
}
