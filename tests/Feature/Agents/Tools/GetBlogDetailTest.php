<?php

use App\Agents\Tools\GetBlogDetail;
use App\Models\Blog;
use Laravel\Ai\Tools\Request;

it('returns full blog content by slug', function () {
    Blog::factory()->published()->create([
        'title' => 'BlogDetail Full Post',
        'slug' => 'blog-detail-test',
        'content' => '<p>Full blog content here</p>',
    ]);

    $tool = new GetBlogDetail;
    $result = $tool->handle(new Request(['slug' => 'blog-detail-test']));

    expect($result)->toContain('BlogDetail Full Post')
        ->toContain('Full blog content here');
});

it('returns not found for nonexistent slug', function () {
    $tool = new GetBlogDetail;
    $result = $tool->handle(new Request(['slug' => 'nonexistent-blog']));

    expect($result)->toBe('Blog post not found.');
});

it('does not return draft blogs', function () {
    Blog::factory()->draft()->create(['slug' => 'draft-blog-detail']);

    $tool = new GetBlogDetail;
    $result = $tool->handle(new Request(['slug' => 'draft-blog-detail']));

    expect($result)->toBe('Blog post not found.');
});
