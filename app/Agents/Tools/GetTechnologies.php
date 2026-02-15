<?php

namespace App\Agents\Tools;

use App\Models\Technology;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetTechnologies implements Tool
{
    public function description(): string
    {
        return "Get a list of technologies Nick works with, including how many published projects use each one. Use this when a user asks about Nick's tech stack, skills, or whether he has experience with a specific technology.";
    }

    public function handle(Request $request): string
    {
        $technologies = Technology::query()
            ->withCount(['projects' => fn ($q) => $q->published()])
            ->orderByDesc('projects_count')
            ->get();

        if ($technologies->isEmpty()) {
            return 'No technologies have been recorded yet.';
        }

        return $technologies->map(fn (Technology $tech) => [
            'name' => $tech->name,
            'slug' => $tech->slug,
            'project_count' => $tech->projects_count,
        ])->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
    {
        return [];
    }
}
