<?php

use App\Jobs\PostToXJob;
use App\Models\Share;
use App\Services\XPostingService;
use Illuminate\Support\Facades\Queue;

it('posts tweet and saves x_post_id', function () {
    Queue::fake();
    $share = Share::factory()->withSummary()->create(['summary' => 'A concise take.', 'post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    config([
        'services.x.api_key' => 'test-key',
        'services.x.api_secret' => 'test-secret',
        'services.x.access_token' => 'test-token',
        'services.x.access_token_secret' => 'test-token-secret',
    ]);

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postTweet')
        ->once()
        ->andReturn(['id' => '123456', 'text' => 'A concise take.']);

    (new PostToXJob($share))->handle($mockService);

    $share->refresh();

    expect($share->x_post_id)->toBe('123456');
});

it('skips posting when post_to_x is false', function () {
    Queue::fake();
    $share = Share::factory()->withSummary()->withoutXPosting()->create();
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postTweet');

    (new PostToXJob($share))->handle($mockService);

    $share->refresh();

    expect($share->x_post_id)->toBeNull();
});

it('skips posting when summary is null', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create(['post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postTweet');

    (new PostToXJob($share))->handle($mockService);

    $share->refresh();

    expect($share->x_post_id)->toBeNull();
});

it('skips posting when already posted', function () {
    Queue::fake();
    $share = Share::factory()->postedToX()->create([
        'summary' => 'Already posted',
        'x_post_id' => '999',
    ]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldNotReceive('postTweet');

    (new PostToXJob($share))->handle($mockService);

    $share->refresh();

    expect($share->x_post_id)->toBe('999');
});

it('lets exceptions propagate so queue retries work', function () {
    Queue::fake();
    $share = Share::factory()->withSummary()->create(['summary' => 'A concise take.', 'post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    config([
        'services.x.api_key' => 'test-key',
        'services.x.api_secret' => 'test-secret',
        'services.x.access_token' => 'test-token',
        'services.x.access_token_secret' => 'test-token-secret',
    ]);

    $mockService = Mockery::mock(XPostingService::class);
    $mockService->shouldReceive('postTweet')
        ->once()
        ->andThrow(new \RuntimeException('X API error'));

    expect(fn () => (new PostToXJob($share))->handle($mockService))
        ->toThrow(\RuntimeException::class, 'X API error');

    $share->refresh();

    expect($share->x_post_id)->toBeNull();
});
