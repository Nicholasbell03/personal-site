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
        '/api/v1/blogs/featured' => Cache::get('api.v1.blogs.featured'),
        '/api/v1/projects/featured' => Cache::get('api.v1.projects.featured'),
        '/api/v1/shares/featured' => Cache::get('api.v1.shares.featured'),
        "/api/v1/blogs/{$blog->slug}" => Cache::get("api.v1.blogs.show.{$blog->slug}"),
        "/api/v1/projects/{$project->slug}" => Cache::get("api.v1.projects.show.{$project->slug}"),
        "/api/v1/shares/{$share->slug}" => Cache::get("api.v1.shares.show.{$share->slug}"),
    ];
    $warmedIndexes = [
        '/api/v1/blogs' => Cache::get('api.v1.blogs.index.1'),
        '/api/v1/projects' => Cache::get('api.v1.projects.index.1'),
        '/api/v1/shares' => Cache::get('api.v1.shares.index.1'),
    ];
    $warmedFeed = Cache::get(FeedCache::KEY);

    Cache::flush();

    foreach ($warmed as $uri => $payload) {
        expect($payload)->not->toBeNull()
            ->and($this->getJson($uri)->assertOk()->json())->toEqual($payload);
    }

    // Pagination links take their base URL from the current request, so compare the items only.
    foreach ($warmedIndexes as $uri => $payload) {
        expect($payload)->not->toBeNull()
            ->and($this->getJson($uri)->assertOk()->json('data'))->toEqual($payload['data']);
    }

    expect($warmedFeed)->toBeString()
        ->and($this->get('/feed')->assertOk()->getContent())->toBe($warmedFeed);
});
