<?php

use App\Agents\Tools\SearchContent;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Models\Technology;
use Laravel\Ai\Tools\Request;

it('returns results matching the query', function () {
    Blog::factory()->published()->create(['title' => 'SearchTool Laravel Guide']);

    $tool = app(SearchContent::class);
    $result = $tool->handle(new Request(['query' => 'SearchTool Laravel']));

    expect($result)->toContain('SearchTool Laravel Guide');
});

it('returns message when no results found', function () {
    $tool = app(SearchContent::class);
    $result = $tool->handle(new Request(['query' => 'zzzznonexistent9999']));

    expect($result)->toBe('No relevant content found for that query.');
});

it('filters by content type', function () {
    Blog::factory()->published()->create(['title' => 'TypeFilter Blog Content']);
    Project::factory()->published()->create(['title' => 'TypeFilter Project Content']);

    $tool = app(SearchContent::class);
    $result = $tool->handle(new Request(['query' => 'TypeFilter', 'type' => 'blog']));

    expect($result)->toContain('TypeFilter Blog Content')
        ->not->toContain('TypeFilter Project Content');
});

it('excludes draft content', function () {
    Blog::factory()->draft()->create(['title' => 'DraftOnly SearchTool']);
    Blog::factory()->published()->create(['title' => 'PublishedOnly SearchTool']);

    $tool = app(SearchContent::class);
    $result = $tool->handle(new Request(['query' => 'SearchTool']));

    expect($result)->toContain('PublishedOnly SearchTool')
        ->not->toContain('DraftOnly SearchTool');
});

it('finds projects by technology name', function () {
    $tech = Technology::factory()->create(['name' => 'TechSearchReact']);
    $project = Project::factory()->published()->create(['title' => 'TechSearchProject Regular Title']);
    $project->technologies()->attach($tech);

    $tool = app(SearchContent::class);
    $result = $tool->handle(new Request(['query' => 'TechSearchReact', 'type' => 'project']));

    expect($result)->toContain('TechSearchProject Regular Title');
});
