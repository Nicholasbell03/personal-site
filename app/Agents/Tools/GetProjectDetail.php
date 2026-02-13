<?php

namespace App\Agents\Tools;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetProjectDetail implements Tool
{
    public function description(): string
    {
        return 'Get full details of a specific project by its slug. Use this when a user wants more information about a particular project.';
    }

    public function handle(Request $request): string
    {
        $project = Project::query()
            ->published()
            ->with('technologies')
            ->where('slug', $request->string('slug'))
            ->first();

        if (! $project) {
            return 'Project not found.';
        }

        return (new ProjectResource($project))->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema
                ->string()
                ->description('The URL slug of the project.')
                ->required(),
        ];
    }
}
