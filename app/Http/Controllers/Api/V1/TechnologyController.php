<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TechnologyResource;
use App\Models\Technology;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TechnologyController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function index(): JsonResponse
    {
        $data = Cache::remember('api.v1.technologies', self::CACHE_TTL, function () {
            $technologies = Technology::query()
                ->featured()
                ->withPublishedProjectsCount()
                ->orderBy('name')
                ->get();

            return TechnologyResource::collection($technologies)->response()->getData(true);
        });

        return response()->json($data);
    }
}
