<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreBlogImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMediaRequest;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request, StoreBlogImage $storeBlogImage): JsonResponse
    {
        return response()->json([
            'data' => $storeBlogImage->execute($request->file('file')),
        ], 201);
    }
}
