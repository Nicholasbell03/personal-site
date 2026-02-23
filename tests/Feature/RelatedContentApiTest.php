<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;

it('returns next blog when viewing an older blog', function () {
    $older = Blog::factory()->published()->create(['published_at' => now()->subDays(2)]);
    $newer = Blog::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/blogs/{$older->slug}/related");

    $response->assertOk();
    expect($response->json('data.next.slug'))->toBe($newer->slug)
        ->and($response->json('data.next.type'))->toBe('blog');
});

it('returns null next when viewing the most recent blog', function () {
    $newest = Blog::factory()->published()->create(['published_at' => now()]);
    Blog::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/blogs/{$newest->slug}/related");

    $response->assertOk();
    expect($response->json('data.next'))->toBeNull();
});

it('excludes draft blogs from next', function () {
    $published = Blog::factory()->published()->create(['published_at' => now()->subDay()]);
    Blog::factory()->draft()->create();

    $response = $this->getJson("/api/v1/blogs/{$published->slug}/related");

    $response->assertOk();
    expect($response->json('data.next'))->toBeNull();
});

it('returns next project when viewing an older project', function () {
    $older = Project::factory()->published()->create(['published_at' => now()->subDays(2)]);
    $newer = Project::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/projects/{$older->slug}/related");

    $response->assertOk();
    expect($response->json('data.next.slug'))->toBe($newer->slug)
        ->and($response->json('data.next.type'))->toBe('project');
});

it('returns next share when viewing an older share', function () {
    $older = Share::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Share::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/shares/{$older->slug}/related");

    $response->assertOk();
    expect($response->json('data.next.slug'))->toBe($newer->slug)
        ->and($response->json('data.next.type'))->toBe('share');
});

it('returns empty related array on SQLite', function () {
    $blog = Blog::factory()->published()->create();

    $response = $this->getJson("/api/v1/blogs/{$blog->slug}/related");

    $response->assertOk();
    expect($response->json('data.related'))->toBeArray()->toBeEmpty();
});

it('returns 404 for non-existent blog slug', function () {
    $response = $this->getJson('/api/v1/blogs/non-existent-slug/related');

    $response->assertNotFound();
});

it('returns 404 for non-existent project slug', function () {
    $response = $this->getJson('/api/v1/projects/non-existent-slug/related');

    $response->assertNotFound();
});

it('returns 404 for non-existent share slug', function () {
    $response = $this->getJson('/api/v1/shares/non-existent-slug/related');

    $response->assertNotFound();
});

it('returns correct json structure for blog related endpoint', function () {
    Blog::factory()->published()->create();

    $blog = Blog::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson("/api/v1/blogs/{$blog->slug}/related");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'next' => [
                    'type',
                    'title',
                    'slug',
                    'description',
                    'image',
                    'published_at',
                ],
                'related',
            ],
        ]);
});

it('uses id as tiebreaker when published_at is the same', function () {
    $publishedAt = now()->subDay();
    $first = Blog::factory()->published()->create(['published_at' => $publishedAt]);
    $second = Blog::factory()->published()->create(['published_at' => $publishedAt]);

    $response = $this->getJson("/api/v1/blogs/{$first->slug}/related");

    $response->assertOk();
    expect($response->json('data.next.slug'))->toBe($second->slug);
});

it('returns 404 for draft blog related endpoint', function () {
    $draft = Blog::factory()->draft()->create();

    $response = $this->getJson("/api/v1/blogs/{$draft->slug}/related");

    $response->assertNotFound();
});

it('returns 404 for draft project related endpoint', function () {
    $draft = Project::factory()->draft()->create();

    $response = $this->getJson("/api/v1/projects/{$draft->slug}/related");

    $response->assertNotFound();
});
