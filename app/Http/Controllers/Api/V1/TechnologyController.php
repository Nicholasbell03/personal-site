<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TechnologyService;
use Illuminate\Http\JsonResponse;

class TechnologyController extends Controller
{
    public function index(TechnologyService $technologyService): JsonResponse
    {
        return response()->json($technologyService->featured());
    }
}
