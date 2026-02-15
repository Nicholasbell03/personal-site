<?php

namespace App\Agents\Tools;

use App\Http\Resources\BlogResource;
use App\Models\Blog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetBlogDetail implements Tool
{
    public function description(): string
    {
        return 'Get the full content of a specific blog post by its slug. Use this when a user wants to know more about a particular blog post.';
    }

    public function handle(Request $request): string
    {
        $blog = Blog::query()
            ->published()
            ->where('slug', $request->string('slug'))
            ->first();

        if (! $blog) {
            return 'Blog post not found.';
        }

        return (new BlogResource($blog))->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema
                ->string()
                ->description('The URL slug of the blog post.')
                ->required(),
        ];
    }
}
