<?php

use App\Agents\Tools\GetProjects;
use App\Models\Project;
use App\Models\Technology;
use Laravel\Ai\Tools\Request;

it('returns published projects', function () {
    Project::factory()->published()->create(['title' => 'GetProjects Published']);

    $tool = new GetProjects;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('GetProjects Published');
});

it('excludes draft projects', function () {
    Project::factory()->draft()->create(['title' => 'GetProjects Draft']);
    Project::factory()->published()->create(['title' => 'GetProjects Visible']);

    $tool = new GetProjects;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('GetProjects Visible')
        ->not->toContain('GetProjects Draft');
});

it('filters to featured projects', function () {
    Project::factory()->published()->create(['title' => 'GetProjects Featured', 'is_featured' => true]);
    Project::factory()->published()->create(['title' => 'GetProjects Regular', 'is_featured' => false]);

    $tool = new GetProjects;
    $result = $tool->handle(new Request(['featured' => true]));

    expect($result)->toContain('GetProjects Featured')
        ->not->toContain('GetProjects Regular');
});

it('respects limit parameter', function () {
    Project::factory()->published()->count(5)->create();

    $tool = new GetProjects;
    $result = json_decode($tool->handle(new Request(['limit' => 2])), true);

    expect($result)->toHaveCount(2);
});

it('returns message when no projects found', function () {
    $tool = new GetProjects;
    $result = $tool->handle(new Request([]));

    expect($result)->toBe('No projects found.');
});

it('filters by technology slug', function () {
    $tech = Technology::factory()->create(['name' => 'React', 'slug' => 'react']);
    $matching = Project::factory()->published()->create(['title' => 'TechFilter React Project']);
    $matching->technologies()->attach($tech);

    Project::factory()->published()->create(['title' => 'TechFilter Other Project']);

    $tool = new GetProjects;
    $result = $tool->handle(new Request(['technology' => 'react']));

    expect($result)->toContain('TechFilter React Project')
        ->not->toContain('TechFilter Other Project');
});

it('filters by technology name', function () {
    $tech = Technology::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $matching = Project::factory()->published()->create(['title' => 'TechName Laravel Project']);
    $matching->technologies()->attach($tech);

    Project::factory()->published()->create(['title' => 'TechName Unrelated Project']);

    $tool = new GetProjects;
    $result = $tool->handle(new Request(['technology' => 'Laravel']));

    expect($result)->toContain('TechName Laravel Project')
        ->not->toContain('TechName Unrelated Project');
});
