<?php

namespace App\Services;

use App\Http\Resources\TechnologyResource;
use App\Models\Technology;
use Illuminate\Support\Facades\Cache;

class TechnologyService
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    /**
     * Featured technologies with their published project counts, as the cached JSON payload.
     * Technology, Project and ProjectTechnology changes forget Technology::CACHE_KEY.
     *
     * @return array<string, mixed>
     */
    public function featured(): array
    {
        return Cache::remember(Technology::CACHE_KEY, self::CACHE_TTL, function () {
            $technologies = Technology::query()
                ->featured()
                ->withPublishedProjectsCount()
                ->orderBy('name')
                ->get();

            return TechnologyResource::collection($technologies)->response()->getData(true);
        });
    }
}
