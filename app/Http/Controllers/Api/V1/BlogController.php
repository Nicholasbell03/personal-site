<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $blogs = Blog::query()
            ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'status', 'published_at', 'read_time'])
            ->published()
            ->latestPublished()
            ->paginate(10);

        return BlogSummaryResource::collection($blogs);
    }

    public function featured(): AnonymousResourceCollection
    {
        $blogs = Blog::query()
            ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'status', 'published_at', 'read_time'])
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
        $blog = Blog::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new BlogResource($blog);
    }
}
