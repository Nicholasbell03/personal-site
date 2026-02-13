<?php

namespace App\Agents\Tools;

use App\Services\SearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchContent implements Tool
{
    public function __construct(
        protected SearchService $searchService,
    ) {}

    public function description(): string
    {
        return 'Search across all site content (blogs, projects, and shares) using semantic and keyword search. Use this when a user asks a question that requires finding relevant content.';
    }

    public function handle(Request $request): string
    {
        $results = $this->searchService->search(
            $request->string('query')->toString(),
            $request->string('type', 'all')->toString(),
        );

        $hasResults = collect($results)->flatten(1)->isNotEmpty();

        if (! $hasResults) {
            return 'No relevant content found for that query.';
        }

        return json_encode($results, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query to find relevant content.')
                ->required(),
            'type' => $schema
                ->string()
                ->description('Content type to search. Options: all, blog, project, share. Defaults to all.')
                ->enum(['all', 'blog', 'project', 'share']),
        ];
    }
}
