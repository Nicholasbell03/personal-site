<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BlogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $blogs = Blog::query()
            ->published()
            ->latestPublished()
            ->paginate(10);

        return BlogSummaryResource::collection($blogs);
    }

    public function featured(): AnonymousResourceCollection
    {
        $blogs = Blog::query()
            ->published()
            ->latestPublished()
            ->limit(3)
            ->get();

        return BlogSummaryResource::collection($blogs);
    }

    public function show(string $slug): BlogResource
    {
        $blog = Blog::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }

    public function preview(string $slug): BlogResource
    {
        $this->validatePreviewToken();

        $blog = Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }

    private function validatePreviewToken(): void
    {
        $token = request()->query('token');
        $validToken = config('app.preview_token');

        if (! $token || ! $validToken || $token !== $validToken) {
            throw new AccessDeniedHttpException('Invalid preview token');
        }
    }
}
