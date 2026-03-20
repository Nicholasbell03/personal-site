<?php

use App\Models\Blog;
use App\Models\Project;

it('returns valid XML with correct content type', function () {
    $response = $this->get('/feed');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    // Verify it's valid XML
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    expect((string) $xml->channel->title)->toBe('Nicholas Bell');
});

it('includes published blogs in feed', function () {
    $blog = Blog::factory()->published()->create(['title' => 'My Test Blog']);

    $response = $this->get('/feed');

    $response->assertOk();
    $xml = simplexml_load_string($response->getContent());

    expect($xml->channel->item)->toHaveCount(1);
    expect((string) $xml->channel->item[0]->title)->toBe('My Test Blog');
    expect((string) $xml->channel->item[0]->category)->toBe('Blog');
});

it('includes published projects in feed', function () {
    $project = Project::factory()->published()->create(['title' => 'My Test Project']);

    $response = $this->get('/feed');

    $response->assertOk();
    $xml = simplexml_load_string($response->getContent());

    expect($xml->channel->item)->toHaveCount(1);
    expect((string) $xml->channel->item[0]->title)->toBe('My Test Project');
    expect((string) $xml->channel->item[0]->category)->toBe('Project');
});

it('excludes draft blogs from feed', function () {
    Blog::factory()->published()->create();
    Blog::factory()->draft()->create();

    $response = $this->get('/feed');

    $xml = simplexml_load_string($response->getContent());
    expect($xml->channel->item)->toHaveCount(1);
});

it('excludes draft projects from feed', function () {
    Project::factory()->published()->create();
    Project::factory()->draft()->create();

    $response = $this->get('/feed');

    $xml = simplexml_load_string($response->getContent());
    expect($xml->channel->item)->toHaveCount(1);
});

it('sorts items by published_at descending', function () {
    $oldBlog = Blog::factory()->published()->create([
        'title' => 'Old Blog',
        'published_at' => now()->subDays(3),
    ]);
    $newProject = Project::factory()->published()->create([
        'title' => 'New Project',
        'published_at' => now()->subDay(),
    ]);
    $newestBlog = Blog::factory()->published()->create([
        'title' => 'Newest Blog',
        'published_at' => now(),
    ]);

    $response = $this->get('/feed');

    $xml = simplexml_load_string($response->getContent());
    expect((string) $xml->channel->item[0]->title)->toBe('Newest Blog');
    expect((string) $xml->channel->item[1]->title)->toBe('New Project');
    expect((string) $xml->channel->item[2]->title)->toBe('Old Blog');
});

it('limits feed to 50 items', function () {
    Blog::factory()->published()->count(30)->create();
    Project::factory()->published()->count(30)->create();

    $response = $this->get('/feed');

    $xml = simplexml_load_string($response->getContent());
    expect($xml->channel->item)->toHaveCount(50);
});

it('uses frontend URLs as item links', function () {
    $blog = Blog::factory()->published()->create(['slug' => 'test-blog']);
    $project = Project::factory()->published()->create(['slug' => 'test-project']);

    $response = $this->get('/feed');

    $xml = simplexml_load_string($response->getContent());
    $items = [];
    foreach ($xml->channel->item as $item) {
        $items[(string) $item->category] = (string) $item->link;
    }

    expect($items['Blog'])->toContain('/blog/test-blog');
    expect($items['Project'])->toContain('/projects/test-project');
});

it('returns empty feed when no published content exists', function () {
    $response = $this->get('/feed');

    $response->assertOk();
    $xml = simplexml_load_string($response->getContent());
    expect($xml->channel->item)->toHaveCount(0);
});
