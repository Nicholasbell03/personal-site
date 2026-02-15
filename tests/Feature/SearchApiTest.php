<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Models\Technology;

it('returns 422 when query is missing', function () {
    $this->getJson('/api/v1/search')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

it('returns 422 when query is too short', function () {
    $this->getJson('/api/v1/search?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

it('returns 422 when type is invalid', function () {
    $this->getJson('/api/v1/search?q=test&type=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns results grouped by type for type=all', function () {
    Blog::factory()->published()->create(['title' => 'Laravel Testing Guide']);
    Project::factory()->published()->create(['title' => 'Laravel Dashboard']);
    Share::factory()->create(['title' => 'Laravel News Article']);

    $response = $this->getJson('/api/v1/search?q=Laravel&type=all');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'blogs',
                'projects',
                'shares',
            ],
        ]);

    expect($response->json('data.blogs'))->toHaveCount(1)
        ->and($response->json('data.projects'))->toHaveCount(1)
        ->and($response->json('data.shares'))->toHaveCount(1);
});

it('defaults to type=all when type is omitted', function () {
    Blog::factory()->published()->create(['title' => 'Search Default Test']);

    $response = $this->getJson('/api/v1/search?q=Search+Default');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'blogs',
                'projects',
                'shares',
            ],
        ]);
});

it('returns only blog results when type=blog', function () {
    Blog::factory()->published()->create(['title' => 'Filtered Blog Post']);
    Project::factory()->published()->create(['title' => 'Filtered Project']);
    Share::factory()->create(['title' => 'Filtered Share']);

    $response = $this->getJson('/api/v1/search?q=Filtered&type=blog');

    $response->assertOk();

    expect($response->json('data'))->toHaveKey('blogs')
        ->not->toHaveKey('projects')
        ->not->toHaveKey('shares');

    expect($response->json('data.blogs'))->toHaveCount(1);
});

it('returns only project results when type=project', function () {
    Blog::factory()->published()->create(['title' => 'TypeFilter Blog']);
    Project::factory()->published()->create(['title' => 'TypeFilter Project']);

    $response = $this->getJson('/api/v1/search?q=TypeFilter&type=project');

    $response->assertOk();

    expect($response->json('data'))->toHaveKey('projects')
        ->not->toHaveKey('blogs')
        ->not->toHaveKey('shares');

    expect($response->json('data.projects'))->toHaveCount(1);
});

it('returns only share results when type=share', function () {
    Share::factory()->create(['title' => 'ShareOnly Test']);

    $response = $this->getJson('/api/v1/search?q=ShareOnly&type=share');

    $response->assertOk();

    expect($response->json('data'))->toHaveKey('shares')
        ->not->toHaveKey('blogs')
        ->not->toHaveKey('projects');

    expect($response->json('data.shares'))->toHaveCount(1);
});

it('excludes draft blogs from results', function () {
    Blog::factory()->published()->create(['title' => 'Published Draft Test']);
    Blog::factory()->draft()->create(['title' => 'Draft Draft Test']);

    $response = $this->getJson('/api/v1/search?q=Draft+Test&type=blog');

    $response->assertOk();

    expect($response->json('data.blogs'))->toHaveCount(1)
        ->and($response->json('data.blogs.0.title'))->toBe('Published Draft Test');
});

it('excludes draft projects from results', function () {
    Project::factory()->published()->create(['title' => 'Published Project Vis']);
    Project::factory()->draft()->create(['title' => 'Draft Project Vis']);

    $response = $this->getJson('/api/v1/search?q=Project+Vis&type=project');

    $response->assertOk();

    expect($response->json('data.projects'))->toHaveCount(1)
        ->and($response->json('data.projects.0.title'))->toBe('Published Project Vis');
});

it('includes all shares regardless of status', function () {
    Share::factory()->count(3)->create(['title' => 'ShareAll Visible']);

    $response = $this->getJson('/api/v1/search?q=ShareAll&type=share');

    $response->assertOk();

    expect($response->json('data.shares'))->toHaveCount(3);
});

it('limits results to 5 per type', function () {
    Blog::factory()->published()->count(8)->create(['title' => 'LimitTest Blog']);

    $response = $this->getJson('/api/v1/search?q=LimitTest&type=blog');

    $response->assertOk();

    expect($response->json('data.blogs'))->toHaveCount(5);
});

it('returns empty arrays when nothing matches', function () {
    $response = $this->getJson('/api/v1/search?q=zzzznonexistent&type=all');

    $response->assertOk();

    expect($response->json('data.blogs'))->toBeEmpty()
        ->and($response->json('data.projects'))->toBeEmpty()
        ->and($response->json('data.shares'))->toBeEmpty();
});

it('returns correct BlogSummaryResource fields', function () {
    Blog::factory()->published()->create(['title' => 'ResourceFields Blog']);

    $response = $this->getJson('/api/v1/search?q=ResourceFields&type=blog');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'blogs' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'published_at',
                        'read_time',
                    ],
                ],
            ],
        ]);

    expect($response->json('data.blogs.0'))->not->toHaveKey('content');
});

it('returns correct ProjectSummaryResource fields', function () {
    Project::factory()->published()->create(['title' => 'ResourceFields Project']);

    $response = $this->getJson('/api/v1/search?q=ResourceFields&type=project');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'projects' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'description',
                        'featured_image',
                        'project_url',
                        'github_url',
                        'technologies',
                        'published_at',
                    ],
                ],
            ],
        ]);

    expect($response->json('data.projects.0'))->not->toHaveKey('long_description');
});

it('returns correct ShareSummaryResource fields', function () {
    Share::factory()->create(['title' => 'ResourceFields Share']);

    $response = $this->getJson('/api/v1/search?q=ResourceFields&type=share');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'shares' => [
                    '*' => [
                        'id',
                        'url',
                        'source_type',
                        'title',
                        'slug',
                        'description',
                        'image_url',
                        'site_name',
                        'author',
                        'commentary',
                        'created_at',
                    ],
                ],
            ],
        ]);
});

it('searches across multiple fields for blogs', function () {
    Blog::factory()->published()->create([
        'title' => 'Regular Title',
        'excerpt' => 'This has a unique ExcerptOnly keyword',
    ]);

    $response = $this->getJson('/api/v1/search?q=ExcerptOnly&type=blog');

    $response->assertOk();

    expect($response->json('data.blogs'))->toHaveCount(1);
});

it('searches across multiple fields for projects', function () {
    Project::factory()->published()->create([
        'title' => 'Regular Title',
        'description' => 'This has DescOnly keyword',
    ]);

    $response = $this->getJson('/api/v1/search?q=DescOnly&type=project');

    $response->assertOk();

    expect($response->json('data.projects'))->toHaveCount(1);
});

it('searches across multiple fields for shares', function () {
    Share::factory()->create([
        'title' => 'Regular Title',
        'commentary' => 'CommentOnly keyword here',
    ]);

    $response = $this->getJson('/api/v1/search?q=CommentOnly&type=share');

    $response->assertOk();

    expect($response->json('data.shares'))->toHaveCount(1);
});

it('finds projects by technology name', function () {
    $tech = Technology::factory()->create(['name' => 'TechApiSearchVue']);
    $project = Project::factory()->published()->create(['title' => 'TechApiProject Plain Title']);
    $project->technologies()->attach($tech);

    $response = $this->getJson('/api/v1/search?q=TechApiSearchVue&type=project');

    $response->assertOk();

    expect($response->json('data.projects'))->toHaveCount(1)
        ->and($response->json('data.projects.0.title'))->toBe('TechApiProject Plain Title');
});
