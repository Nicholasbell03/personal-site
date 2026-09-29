<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Support\Facades\Cache;

/**
 * Index lists are cached for 24 hours and shared by every visitor. Pagination links are built per
 * request, so the Host or X-Forwarded-Host of whichever request filled the cache never reaches others.
 */
it('ignores a forged host when caching pagination links', function (string $type, Closure $seed) {
    $seed();
    Cache::flush();

    $this->getJson("/api/v1/{$type}", ['X-Forwarded-Host' => 'evil.example'])->assertOk();

    // The test client builds a relative URL from the previous request's host, so address the
    // honest visitor's request explicitly, as a real client would.
    $base = rtrim(config('app.url'), '/');
    $links = $this->getJson("{$base}/api/v1/{$type}")->assertOk()->json('links');

    expect(json_encode(Cache::get("api.v1.{$type}.index")))->not->toContain('evil.example')
        ->and(json_encode($links))->not->toContain('evil.example')
        ->and($links['next'])->toBe("{$base}/api/v1/{$type}?page=2");
})->with([
    'blogs' => ['blogs', fn () => Blog::factory()->count(11)->published()->create()],
    'projects' => ['projects', fn () => Project::factory()->count(11)->published()->create()],
    'shares' => ['shares', fn () => Share::factory()->count(11)->create()],
]);

it('keeps every page in step when content changes', function () {
    Blog::factory()->count(21)->published()->create(['published_at' => now()->subDay()]);

    expect($this->getJson('/api/v1/blogs?page=3')->assertOk()->json('data'))->toHaveCount(1);

    // A newer post pushes one item from each page onto the next, so page 3 must refresh too.
    Blog::factory()->published()->create(['published_at' => now()]);

    $this->getJson('/api/v1/blogs?page=3')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 22);
});

it('treats a page below 1 as the first page', function (int $page) {
    Blog::factory()->count(11)->published()->create();

    $this->getJson("/api/v1/blogs?page={$page}")
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.current_page', 1);
})->with([0, -1]);
