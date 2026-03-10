<?php

use App\Exceptions\XCreditsDepletedException;
use App\Jobs\PostContentToXJob;
use App\Models\Blog;
use App\Models\Project;
use App\Services\XPostingService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.x.api_key' => 'test-key',
        'services.x.api_secret' => 'test-secret',
        'services.x.access_token' => 'test-token',
        'services.x.access_token_secret' => 'test-token-secret',
    ]);
});

it('posts tweet and saves x_post_id for blog', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'A great blog post about testing.',
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->andReturn(['id' => '123456', 'text' => 'A great blog post about testing.']);

    (new PostContentToXJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->x_post_id)->toBe('123456');
});

it('posts tweet and saves x_post_id for project', function () {
    Queue::fake();

    $project = Project::factory()->published()->create([
        'description' => 'An awesome project.',
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->andReturn(['id' => '789012', 'text' => 'An awesome project.']);

    (new PostContentToXJob($project))->handle($mockService);

    $project->refresh();

    expect($project->x_post_id)->toBe('789012');
});

it('skips posting when post_to_x is false', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create(['post_to_x' => false]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postText');

    (new PostContentToXJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->x_post_id)->toBeNull();
});

it('skips posting when already posted', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->postedToX()->create();

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postText');

    (new PostContentToXJob($blog))->handle($mockService);
});

it('skips posting when x credentials not configured', function () {
    config([
        'services.x.api_key' => null,
        'services.x.api_secret' => null,
        'services.x.access_token' => null,
        'services.x.access_token_secret' => null,
    ]);

    Queue::fake();

    $blog = Blog::factory()->published()->create(['post_to_x' => true]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postText');

    (new PostContentToXJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->x_post_id)->toBeNull();
});

it('skips posting when model was already posted by another process', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    // Simulate another process posting first (before the job re-fetches)
    Blog::query()->whereKey($blog->id)->update(['x_post_id' => 'race-winner']);

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postText');

    (new PostContentToXJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->x_post_id)->toBe('race-winner');
});

it('fails permanently when x credits are depleted', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->andThrow(new XCreditsDepletedException('Credits depleted'));

    $job = new PostContentToXJob($blog);
    $job->handle($mockService);

    $blog->refresh();

    expect($blog->x_post_id)->toBeNull();
    expect($job->job)->toBeNull();
});

it('lets runtime exceptions propagate for queue retries', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->andThrow(new \RuntimeException('X API error'));

    expect(fn () => (new PostContentToXJob($blog))->handle($mockService))
        ->toThrow(\RuntimeException::class, 'X API error');
});

it('truncates long descriptions to fit tweet limit', function () {
    Queue::fake();

    $longDescription = str_repeat('a', 300);

    $blog = Blog::factory()->published()->create([
        'excerpt' => $longDescription,
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->withArgs(function (string $text) {
            // 255 chars description + 2 \n\n + URL
            $lines = explode("\n\n", $text);

            return mb_strlen($lines[0]) <= 255;
        })
        ->andReturn(['id' => '123', 'text' => 'truncated']);

    (new PostContentToXJob($blog))->handle($mockService);
});

it('posts url only when description is empty', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => null,
        'meta_description' => null,
        'post_to_x' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postText')
        ->once()
        ->withArgs(function (string $text) {
            return ! str_contains($text, "\n\n");
        })
        ->andReturn(['id' => '456', 'text' => 'url only']);

    (new PostContentToXJob($blog))->handle($mockService);
});
