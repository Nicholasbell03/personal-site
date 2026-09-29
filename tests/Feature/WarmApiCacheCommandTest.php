<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Support\FeedCache;
use Illuminate\Support\Facades\Cache;

it('warms the same payloads the public endpoints serve', function () {
    $blog = Blog::factory()->published()->create();
    $project = Project::factory()->published()->create();
    $share = Share::factory()->create();
    Cache::flush();

    $this->artisan('api:warm-cache')->assertSuccessful();

    $warmed = [
        '/api/v1/blogs' => Cache::get('api.v1.blogs.index.1'),
        '/api/v1/projects' => Cache::get('api.v1.projects.index.1'),
        '/api/v1/shares' => Cache::get('api.v1.shares.index.1'),
        '/api/v1/blogs/featured' => Cache::get('api.v1.blogs.featured'),
        '/api/v1/projects/featured' => Cache::get('api.v1.projects.featured'),
        '/api/v1/shares/featured' => Cache::get('api.v1.shares.featured'),
        "/api/v1/blogs/{$blog->slug}" => Cache::get("api.v1.blogs.show.{$blog->slug}"),
        "/api/v1/projects/{$project->slug}" => Cache::get("api.v1.projects.show.{$project->slug}"),
        "/api/v1/shares/{$share->slug}" => Cache::get("api.v1.shares.show.{$share->slug}"),
    ];
    $warmedFeed = Cache::get(FeedCache::KEY);

    Cache::flush();

    foreach ($warmed as $uri => $payload) {
        expect($payload)->not->toBeNull()
            ->and($this->getJson($uri)->assertOk()->json())->toEqual($payload);
    }

    expect($warmedFeed)->toBeString()
        ->and($this->get('/feed')->assertOk()->getContent())->toBe($warmedFeed);
});

it('builds pagination links for the endpoint, not the request that triggered warming', function () {
    Blog::factory()->count(11)->published()->create();
    Project::factory()->count(11)->published()->create();
    Share::factory()->count(11)->create();
    Cache::flush();

    // cronjob.org warms the cache over HTTP, so the command runs inside a GET /api/warm-cache request.
    $this->getJson('/api/warm-cache')->assertOk();

    foreach (['blogs', 'projects', 'shares'] as $type) {
        $warmed = Cache::get("api.v1.{$type}.index.1");

        expect($warmed['meta']['path'])->toBe(url("/api/v1/{$type}"))
            ->and($warmed['links']['next'])->toBe(url("/api/v1/{$type}?page=2"));
    }
});
