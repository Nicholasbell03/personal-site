<?php

use App\Agents\Tools\GetBlogs;
use App\Models\Blog;
use Laravel\Ai\Tools\Request;

it('returns published blogs', function () {
    Blog::factory()->published()->create(['title' => 'GetBlogs Published Post']);

    $tool = new GetBlogs;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('GetBlogs Published Post');
});

it('excludes draft blogs', function () {
    Blog::factory()->draft()->create(['title' => 'GetBlogs Draft Post']);
    Blog::factory()->published()->create(['title' => 'GetBlogs Visible Post']);

    $tool = new GetBlogs;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('GetBlogs Visible Post')
        ->not->toContain('GetBlogs Draft Post');
});

it('respects limit parameter', function () {
    Blog::factory()->published()->count(5)->create();

    $tool = new GetBlogs;
    $result = json_decode($tool->handle(new Request(['limit' => 2])), true);

    expect($result)->toHaveCount(2);
});

it('caps limit at 10', function () {
    Blog::factory()->published()->count(12)->create();

    $tool = new GetBlogs;
    $result = json_decode($tool->handle(new Request(['limit' => 20])), true);

    expect($result)->toHaveCount(10);
});

it('returns message when no blogs found', function () {
    $tool = new GetBlogs;
    $result = $tool->handle(new Request([]));

    expect($result)->toBe('No blog posts found.');
});
