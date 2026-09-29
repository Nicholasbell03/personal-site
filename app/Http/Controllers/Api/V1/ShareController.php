<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateShare;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShareRequest;
use App\Http\Resources\ShareResource;
use App\Services\ShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function __construct(private ShareService $shares) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->shares->paginated($request->integer('page', 1)));
    }

    public function featured(): JsonResponse
    {
        return response()->json($this->shares->featured());
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json($this->shares->show($slug));
    }

    public function related(string $slug): JsonResponse
    {
        return response()->json($this->shares->related($slug));
    }

    /**
     * Create a share.
     *
     * Missing `title` and `description` are filled from the page's Open Graph data. Non-fatal problems
     * (e.g. summary generation failing) are returned in `meta.warnings`.
     */
    public function store(StoreShareRequest $request, CreateShare $createShare): JsonResponse
    {
        $share = $createShare->execute($request->validated());

        $resource = new ShareResource($share);

        if ($share->creationWarnings !== []) {
            $resource->additional(['meta' => ['warnings' => $share->creationWarnings]]);
        }

        return $resource->response()->setStatusCode(201);
    }
}
