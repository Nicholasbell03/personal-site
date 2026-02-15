<?php

use App\Agents\Tools\GetTechnologies;
use App\Models\Project;
use App\Models\Technology;
use Laravel\Ai\Tools\Request;

it('returns all technologies with published project counts', function () {
    $tech = Technology::factory()->create(['name' => 'Laravel']);
    $tech->projects()->attach(
        Project::factory()->published()->create()
    );

    $tool = new GetTechnologies;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('Laravel')
        ->toContain('"project_count": 1');
});

it('only counts published projects', function () {
    $tech = Technology::factory()->create(['name' => 'React']);
    $tech->projects()->attach(
        Project::factory()->published()->create()
    );
    $tech->projects()->attach(
        Project::factory()->draft()->create()
    );

    $tool = new GetTechnologies;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('"project_count": 1');
});

it('returns empty message when no technologies exist', function () {
    $tool = new GetTechnologies;
    $result = $tool->handle(new Request([]));

    expect($result)->toBe('No technologies have been recorded yet.');
});

it('orders by project count descending', function () {
    $popular = Technology::factory()->create(['name' => 'PopularTech']);
    $popular->projects()->attach(
        Project::factory()->published()->count(3)->create()
    );

    $less = Technology::factory()->create(['name' => 'LessTech']);
    $less->projects()->attach(
        Project::factory()->published()->create()
    );

    $tool = new GetTechnologies;
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result[0]['name'])->toBe('PopularTech')
        ->and($result[1]['name'])->toBe('LessTech');
});

it('returns all technologies not just featured', function () {
    Technology::factory()->featured()->create(['name' => 'FeaturedTech']);
    Technology::factory()->create(['name' => 'UnfeaturedTech']);

    $tool = new GetTechnologies;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('FeaturedTech')
        ->toContain('UnfeaturedTech');
});
