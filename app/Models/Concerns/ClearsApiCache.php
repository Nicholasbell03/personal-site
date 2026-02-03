<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait ClearsApiCache
{
    public static function bootClearsApiCache(): void
    {
        static::saved(function ($model) {
            $model->clearApiCache();
        });

        static::deleted(function ($model) {
            $model->clearApiCache();
        });
    }

    public function clearApiCache(): void
    {
        $cacheKey = static::getApiCacheKey();

        // Clear list/featured caches
        Cache::forget("{$cacheKey}.index.1");
        Cache::forget("{$cacheKey}.featured");

        // Clear individual item cache
        Cache::forget("{$cacheKey}.show.{$this->slug}");

        // Clear additional pages (first 10 pages should cover most cases)
        for ($page = 2; $page <= 10; $page++) {
            Cache::forget("{$cacheKey}.index.{$page}");
        }
    }

    abstract public static function getApiCacheKey(): string;
}
