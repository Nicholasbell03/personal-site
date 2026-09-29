<?php

namespace App\Models\Concerns;

use App\Support\FeedCache;
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

        // Clear the index list (every page is sliced from it) and featured caches
        Cache::forget("{$cacheKey}.index");
        Cache::forget("{$cacheKey}.featured");

        // Clear individual item cache
        Cache::forget("{$cacheKey}.show.{$this->slug}");
        Cache::forget("{$cacheKey}.related.{$this->slug}");

        Cache::forget(FeedCache::KEY);
    }

    abstract public static function getApiCacheKey(): string;
}
