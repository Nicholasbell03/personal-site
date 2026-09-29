<?php

namespace App\Actions;

use App\Enums\PublishStatus;
use App\Models\Project;
use Illuminate\Support\Arr;

class CreateProjectDraft
{
    /**
     * @param  array{title: string, slug?: string|null, description?: string|null, long_description?: string|null, project_url?: string|null, github_url?: string|null, technologies?: list<int>|null}  $attributes
     */
    public function execute(array $attributes): Project
    {
        $project = Project::create([
            ...Arr::except($attributes, 'technologies'),
            'status' => PublishStatus::Draft,
        ]);

        if (! empty($attributes['technologies'])) {
            $project->technologies()->attach($attributes['technologies']);
            $project->load('technologies');
        }

        return $project;
    }
}
