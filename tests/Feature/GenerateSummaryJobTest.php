<?php

use App\Jobs\GenerateSummaryJob;
use App\Models\Share;
use App\Services\SummaryService;
use Illuminate\Support\Facades\Queue;

it('generates and saves summary', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create();
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(SummaryService::class);
    $mockService->shouldReceive('generate')
        ->once()
        ->with($share)
        ->andReturn('A concise take.');

    (new GenerateSummaryJob($share))->handle($mockService);

    $share->refresh();

    expect($share->summary)->toBe('A concise take.');
});

it('skips generation when summary already exists', function () {
    Queue::fake();
    $share = Share::factory()->withSummary()->create(['summary' => 'Existing summary']);
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(SummaryService::class);
    $mockService->shouldNotReceive('generate');

    (new GenerateSummaryJob($share))->handle($mockService);

    $share->refresh();

    expect($share->summary)->toBe('Existing summary');
});

it('does not save when generation returns null', function () {
    Queue::fake();
    $share = Share::factory()->withoutSummary()->create();
    Queue::swap(new \Illuminate\Support\Testing\Fakes\QueueFake(app()));

    $mockService = Mockery::mock(SummaryService::class);
    $mockService->shouldReceive('generate')
        ->once()
        ->andReturn(null);

    (new GenerateSummaryJob($share))->handle($mockService);

    $share->refresh();

    expect($share->summary)->toBeNull();
});
