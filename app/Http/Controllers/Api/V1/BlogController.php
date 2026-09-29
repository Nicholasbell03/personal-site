<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBlogDraft;
use App\Actions\UpdateBlogDraft;
use App\Filament\Resources\Blogs\BlogResource as FilamentBlogResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBlogRequest;
use App\Http\Requests\Api\V1\UpdateBlogRequest;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(private BlogService $blogs) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->blogs->paginated($request->integer('page', 1)));
    }

    public function featured(): JsonResponse
    {
        return response()->json($this->blogs->featured());
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json($this->blogs->show($slug));
    }

    public function related(string $slug): JsonResponse
    {
        return response()->json($this->blogs->related($slug));
    }

    /**
     * Create a blog draft.
     *
     * Always creates a draft, whatever `status` is sent. Set `featured_image` to a `path` or `url`
     * returned by `POST /api/v1/media`, and embed image URLs directly in the `content` HTML.
     */
    public function store(StoreBlogRequest $request, CreateBlogDraft $createBlogDraft): JsonResponse
    {
        $blog = $createBlogDraft->execute($request->validated());

        return $this->draftResponse($blog, 201);
    }

    /**
     * Update a blog draft.
     *
     * Partial update: only the fields sent are changed, and `featured_image: null` removes the image.
     * Published blogs return 409, so a live post can never be changed through the API.
     */
    public function update(UpdateBlogRequest $request, Blog $blog, UpdateBlogDraft $updateBlogDraft): JsonResponse
    {
        $blog = $updateBlogDraft->execute($blog, $request->validated());

        return $this->draftResponse($blog, 200);
    }

    public function preview(string $slug): BlogResource
    {
        return new BlogResource($this->blogs->findForPreview($slug));
    }

    private function draftResponse(Blog $blog, int $status): JsonResponse
    {
        return (new BlogResource($blog))
            ->additional(['admin_url' => FilamentBlogResource::getUrl('edit', ['record' => $blog])])
            ->response()
            ->setStatusCode($status);
    }
}
