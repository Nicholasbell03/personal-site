<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Cache;

class ProjectTechnology extends Pivot
{
    protected $table = 'project_technology';

    protected static function booted(): void
    {
        static::created(function () {
            Cache::forget(Technology::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(Technology::CACHE_KEY);
        });
    }
}
