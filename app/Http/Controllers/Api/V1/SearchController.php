<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchService $searchService): JsonResponse
    {
        $results = $searchService->search(
            $request->string('q')->toString(),
            $request->string('type', 'all')->toString(),
        );

        return response()->json(['data' => $results]);
    }
}
