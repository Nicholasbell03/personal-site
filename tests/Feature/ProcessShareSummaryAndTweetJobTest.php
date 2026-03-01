<?php

use App\Jobs\ProcessShareSummaryAndTweetJob;
use App\Models\Share;
use App\Services\SummaryService;
use App\Services\XPostingService;
use Illuminate\Support\Facades\Queue;

it('saves summary to share when generated', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create();
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockSummaryService = Mockery::mock(SummaryService::class);
    $mockSummaryService->shouldReceive('generate')
        ->once()
        ->with($share)
        ->andReturn('A concise take.');

    $mockXService = Mockery::mock(XPostingService::class);
    $mockXService->shouldReceive('postTweet')
        ->once()
        ->andReturn(['id' => '123456', 'text' => 'A concise take.']);

    (new ProcessShareSummaryAndTweetJob($share))
        ->handle($mockSummaryService, $mockXService);

    $share->refresh();

    expect($share->summary)->toBe('A concise take.')
        ->and($share->x_post_id)->toBe('123456');
});

it('skips x posting when post_to_x is false', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->withoutXPosting()->create();
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockSummaryService = Mockery::mock(SummaryService::class);
    $mockSummaryService->shouldReceive('generate')
        ->once()
        ->andReturn('A concise take.');

    $mockXService = Mockery::mock(XPostingService::class);
    $mockXService->shouldNotReceive('postTweet');

    (new ProcessShareSummaryAndTweetJob($share))
        ->handle($mockSummaryService, $mockXService);

    $share->refresh();

    expect($share->summary)->toBe('A concise take.')
        ->and($share->x_post_id)->toBeNull();
});

it('skips x posting when skipXPosting flag is true', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create(['post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockSummaryService = Mockery::mock(SummaryService::class);
    $mockSummaryService->shouldReceive('generate')
        ->once()
        ->andReturn('A concise take.');

    $mockXService = Mockery::mock(XPostingService::class);
    $mockXService->shouldNotReceive('postTweet');

    (new ProcessShareSummaryAndTweetJob($share, skipXPosting: true))
        ->handle($mockSummaryService, $mockXService);

    $share->refresh();

    expect($share->summary)->toBe('A concise take.')
        ->and($share->x_post_id)->toBeNull();
});

it('skips x posting when summary generation returns null', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create(['post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockSummaryService = Mockery::mock(SummaryService::class);
    $mockSummaryService->shouldReceive('generate')
        ->once()
        ->andReturn(null);

    $mockXService = Mockery::mock(XPostingService::class);
    $mockXService->shouldNotReceive('postTweet');

    (new ProcessShareSummaryAndTweetJob($share))
        ->handle($mockSummaryService, $mockXService);

    $share->refresh();

    expect($share->summary)->toBeNull()
        ->and($share->x_post_id)->toBeNull();
});

it('dispatches job when share is created', function () {
    Queue::fake();

    $share = Share::factory()->withoutSummary()->create();

    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, function ($job) use ($share) {
        return $job->share->id === $share->id;
    });
});

it('logs error when x posting fails but still saves summary', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create(['post_to_x' => true]);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockSummaryService = Mockery::mock(SummaryService::class);
    $mockSummaryService->shouldReceive('generate')
        ->once()
        ->andReturn('A concise take.');

    $mockXService = Mockery::mock(XPostingService::class);
    $mockXService->shouldReceive('postTweet')
        ->once()
        ->andThrow(new \RuntimeException('X API error'));

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'X posting failed'));

    (new ProcessShareSummaryAndTweetJob($share))
        ->handle($mockSummaryService, $mockXService);

    $share->refresh();

    expect($share->summary)->toBe('A concise take.')
        ->and($share->x_post_id)->toBeNull();
});
