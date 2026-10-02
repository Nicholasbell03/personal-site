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

    $warmedIndexes = [
        '/api/v1/blogs' => Cache::get('api.v1.blogs.index'),
        '/api/v1/projects' => Cache::get('api.v1.projects.index'),
        '/api/v1/shares' => Cache::get('api.v1.shares.index'),
    ];
    $warmed = [
        '/api/v1/blogs/featured' => Cache::get('api.v1.blogs.featured'),
        '/api/v1/projects/featured' => Cache::get('api.v1.projects.featured'),
        '/api/v1/shares/featured' => Cache::get('api.v1.shares.featured'),
        "/api/v1/blogs/{$blog->slug}" => Cache::get("api.v1.blogs.show.{$blog->slug}"),
        "/api/v1/projects/{$project->slug}" => Cache::get("api.v1.projects.show.{$project->slug}"),
        "/api/v1/shares/{$share->slug}" => Cache::get("api.v1.shares.show.{$share->slug}"),
    ];
    $warmedFeed = Cache::get(FeedCache::KEY);

    Cache::flush();

    // Index caches hold the whole list; each page is sliced from it.
    foreach ($warmedIndexes as $uri => $list) {
        expect($list)->not->toBeNull()
            ->and($this->getJson($uri)->assertOk()->json('data'))->toEqual($list);
    }

    foreach ($warmed as $uri => $payload) {
        expect($payload)->not->toBeNull()
            ->and($this->getJson($uri)->assertOk()->json())->toEqual($payload);
    }

    expect($warmedFeed)->toBeString()
        ->and($this->get('/feed')->assertOk()->getContent())->toBe($warmedFeed);
});

it('serves pagination links for the endpoint after warming over http', function () {
    Blog::factory()->count(11)->published()->create();
    Project::factory()->count(11)->published()->create();
    Share::factory()->count(11)->create();
    Cache::flush();

    // cronjob.org warms the cache over HTTP, so the command runs inside a GET /api/warm-cache request.
    $this->withHeader('X-Cron-Secret', 'test-cron-secret')->getJson('/api/warm-cache')->assertOk();

    foreach (['blogs', 'projects', 'shares'] as $type) {
        $page = $this->getJson("/api/v1/{$type}")->assertOk();

        expect($page->json('meta.path'))->toBe(url("/api/v1/{$type}"))
            ->and($page->json('links.next'))->toBe(url("/api/v1/{$type}?page=2"))
            ->and(json_encode(Cache::get("api.v1.{$type}.index")))->not->toContain('warm-cache');
    }
});
