<?php

use App\Exceptions\LinkedInPermissionDeniedException;
use App\Exceptions\LinkedInTokenExpiredException;
use App\Jobs\PostToLinkedInJob;
use App\Models\Blog;
use App\Models\Project;
use App\Services\LinkedInPostingService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.linkedin.access_token' => 'test-token',
        'services.linkedin.person_id' => 'test-person-id',
    ]);
});

it('posts to linkedin and saves linkedin_post_id for blog', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'A great blog post.',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldReceive('post')
        ->once()
        ->andReturn('urn:li:share:123456');

    (new PostToLinkedInJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBe('urn:li:share:123456');
});

it('posts to linkedin and saves linkedin_post_id for project', function () {
    Queue::fake();

    $project = Project::factory()->published()->create([
        'description' => 'An awesome project.',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldReceive('post')
        ->once()
        ->andReturn('urn:li:share:789012');

    (new PostToLinkedInJob($project))->handle($mockService);

    $project->refresh();

    expect($project->linkedin_post_id)->toBe('urn:li:share:789012');
});

it('skips posting when post_to_linkedin is false', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create(['post_to_linkedin' => false]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldNotReceive('post');

    (new PostToLinkedInJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBeNull();
});

it('skips posting when already posted', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->postedToLinkedIn()->create();

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldNotReceive('post');

    (new PostToLinkedInJob($blog))->handle($mockService);
});

it('skips posting when linkedin credentials not configured', function () {
    config([
        'services.linkedin.access_token' => null,
        'services.linkedin.person_id' => null,
    ]);

    Queue::fake();

    $blog = Blog::factory()->published()->create(['post_to_linkedin' => true]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldNotReceive('post');

    (new PostToLinkedInJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBeNull();
});

it('fails permanently when linkedin token is expired', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldReceive('post')
        ->once()
        ->andThrow(new LinkedInTokenExpiredException('Token expired'));

    $job = new PostToLinkedInJob($blog);
    $job->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBeNull();
    expect($job->job)->toBeNull();
});

it('fails permanently when linkedin permission is denied', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldReceive('post')
        ->once()
        ->andThrow(new LinkedInPermissionDeniedException('Permission denied'));

    $job = new PostToLinkedInJob($blog);
    $job->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBeNull();
    expect($job->job)->toBeNull();
});

it('lets runtime exceptions propagate for queue retries', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldReceive('post')
        ->once()
        ->andThrow(new \RuntimeException('LinkedIn API error'));

    expect(fn () => (new PostToLinkedInJob($blog))->handle($mockService))
        ->toThrow(\RuntimeException::class, 'LinkedIn API error');
});

it('skips posting when model was already posted by another process', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create([
        'excerpt' => 'Test excerpt',
        'post_to_linkedin' => true,
    ]);

    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    // Simulate another process posting first (before the job re-fetches)
    Blog::query()->whereKey($blog->id)->update(['linkedin_post_id' => 'urn:li:share:race-winner']);

    $mockService = Mockery::mock(LinkedInPostingService::class);
    $mockService->shouldNotReceive('post');

    (new PostToLinkedInJob($blog))->handle($mockService);

    $blog->refresh();

    expect($blog->linkedin_post_id)->toBe('urn:li:share:race-winner');
});
