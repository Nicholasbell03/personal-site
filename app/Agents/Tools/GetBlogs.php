<?php

namespace App\Agents\Tools;

use App\Http\Resources\BlogSummaryResource;
use App\Models\Blog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetBlogs implements Tool
{
    public function description(): string
    {
        return 'Get a list of recent blog posts sorted by date. Use this when the user wants to browse or list posts (e.g. "Show me Nick\'s latest posts"). Do NOT use this to search for posts about a specific topic — use SearchContent instead.';
    }

    public function handle(Request $request): string
    {
        $limit = min($request->integer('limit', 3), 5);

        $blogs = Blog::query()
            ->published()
            ->latestPublished()
            ->limit($limit)
            ->get();

        if ($blogs->isEmpty()) {
            return 'No blog posts found.';
        }

        return BlogSummaryResource::collection($blogs)->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema
                ->integer()
                ->description('Number of blog posts to return (1-5, default 3).'),
        ];
    }
}
