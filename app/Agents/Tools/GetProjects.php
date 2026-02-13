<?php

namespace App\Agents\Tools;

use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetProjects implements Tool
{
    public function description(): string
    {
        return 'Get a list of published projects. Use this to answer questions about what Nick has built or worked on.';
    }

    public function handle(Request $request): string
    {
        $limit = min($request->integer('limit', 5), 10);
        $featured = $request->boolean('featured');

        $query = Project::query()->published()->with('technologies');

        if ($featured) {
            $query->featured();
        }

        $projects = $query->latestPublished()->limit($limit)->get();

        if ($projects->isEmpty()) {
            return 'No projects found.';
        }

        return ProjectSummaryResource::collection($projects)->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema
                ->integer()
                ->description('Number of projects to return (1-10, default 5).'),
            'featured' => $schema
                ->boolean()
                ->description('Only return featured projects.'),
        ];
    }
}
