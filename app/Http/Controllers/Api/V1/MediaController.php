<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreBlogImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMediaRequest;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    /**
     * Upload a blog image.
     *
     * Send the image as the multipart `file` field. Embed the returned `url` in blog content, or pass
     * `path` (or `url`) as a blog's `featured_image`.
     */
    public function store(StoreMediaRequest $request, StoreBlogImage $storeBlogImage): JsonResponse
    {
        /**
         * @status 201
         *
         * @body array{data: array{path: string, url: string}}
         */
        return response()->json([
            'data' => $storeBlogImage->execute($request->file('file')),
        ], 201);
    }
}
