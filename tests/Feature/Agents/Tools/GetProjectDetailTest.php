<?php

use App\Agents\Tools\GetProjectDetail;
use App\Models\Project;
use Laravel\Ai\Tools\Request;

it('returns full project details by slug', function () {
    Project::factory()->published()->create([
        'title' => 'ProjectDetail Full Project',
        'slug' => 'project-detail-test',
        'long_description' => 'Detailed project description here',
    ]);

    $tool = new GetProjectDetail;
    $result = $tool->handle(new Request(['slug' => 'project-detail-test']));

    expect($result)->toContain('ProjectDetail Full Project')
        ->toContain('Detailed project description here');
});

it('returns not found for nonexistent slug', function () {
    $tool = new GetProjectDetail;
    $result = $tool->handle(new Request(['slug' => 'nonexistent-project']));

    expect($result)->toBe('Project not found.');
});

it('does not return draft projects', function () {
    Project::factory()->draft()->create(['slug' => 'draft-project-detail']);

    $tool = new GetProjectDetail;
    $result = $tool->handle(new Request(['slug' => 'draft-project-detail']));

    expect($result)->toBe('Project not found.');
});
