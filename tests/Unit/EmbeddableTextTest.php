<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;

it('builds embeddable text for blog with all fields', function () {
    $blog = Blog::factory()->make([
        'title' => 'My Blog Title',
        'excerpt' => 'A short excerpt',
        'content' => '<p>Some <strong>HTML</strong> content</p>',
    ]);

    $text = $blog->getEmbeddableText();

    expect($text)
        ->toContain('My Blog Title')
        ->toContain('A short excerpt')
        ->toContain('Some HTML content')
        ->not->toContain('<p>')
        ->not->toContain('<strong>');
});

it('builds embeddable text for blog with null fields', function () {
    $blog = Blog::factory()->make([
        'title' => 'Just a Title',
        'excerpt' => null,
        'content' => '',
    ]);

    $text = $blog->getEmbeddableText();

    expect($text)->toContain('Just a Title');
});

it('builds embeddable text for project without technologies', function () {
    $project = Project::factory()->make([
        'title' => 'Solo Project',
        'description' => 'No tech',
        'long_description' => null,
    ]);

    $text = $project->getEmbeddableText();

    expect($text)
        ->toContain('Solo Project')
        ->toContain('No tech')
        ->not->toContain('Technologies:');
});

it('builds embeddable text for share with commentary', function () {
    $share = Share::factory()->make([
        'title' => 'Interesting Article',
        'description' => 'Article description',
        'commentary' => 'I found this really insightful',
    ]);

    $text = $share->getEmbeddableText();

    expect($text)
        ->toContain('Interesting Article')
        ->toContain('Article description')
        ->toContain('My thoughts: I found this really insightful');
});

it('builds embeddable text for share without commentary', function () {
    $share = Share::factory()->make([
        'title' => 'Link Only',
        'description' => 'Just a link',
        'commentary' => null,
    ]);

    $text = $share->getEmbeddableText();

    expect($text)
        ->toContain('Link Only')
        ->toContain('Just a link')
        ->not->toContain('My thoughts:');
});

it('returns correct embeddable fields for blog', function () {
    $blog = Blog::factory()->make();

    expect($blog->getEmbeddableFields())->toBe(['title', 'excerpt', 'content']);
});

it('returns correct embeddable fields for project', function () {
    $project = Project::factory()->make();

    expect($project->getEmbeddableFields())->toBe(['title', 'description', 'long_description']);
});

it('returns correct embeddable fields for share', function () {
    $share = Share::factory()->make();

    expect($share->getEmbeddableFields())->toBe(['title', 'description', 'commentary']);
});
