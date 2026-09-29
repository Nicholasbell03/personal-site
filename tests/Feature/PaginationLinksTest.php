<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Support\Facades\Cache;

/**
 * Paginated responses are cached for 24 hours and shared by every visitor, so their links must come
 * from APP_URL, never from the Host or X-Forwarded-Host of whichever request filled the cache.
 */
it('ignores a forged host when caching pagination links', function (string $type, Closure $seed) {
    $seed();
    Cache::flush();

    $this->getJson("/api/v1/{$type}", ['X-Forwarded-Host' => 'evil.example'])->assertOk();

    $links = $this->getJson("/api/v1/{$type}")->assertOk()->json('links');

    expect(json_encode($links))->not->toContain('evil.example')
        ->and($links['next'])->toBe(rtrim(config('app.url'), '/')."/api/v1/{$type}?page=2");
})->with([
    'blogs' => ['blogs', fn () => Blog::factory()->count(11)->published()->create()],
    'projects' => ['projects', fn () => Project::factory()->count(11)->published()->create()],
    'shares' => ['shares', fn () => Share::factory()->count(11)->create()],
]);
