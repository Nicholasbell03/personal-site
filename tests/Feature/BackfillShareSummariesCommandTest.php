<?php

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use App\Models\Share;
use Illuminate\Support\Facades\Queue;

it('dispatches summary jobs for shares without summaries', function () {
    Queue::fake();

    // Create shares — each triggers a chained GenerateSummaryJob + PostToXJob via HasSummary trait
    Share::factory()->withoutSummary()->count(3)->create();
    Share::factory()->withSummary()->count(2)->create();

    // 5 GenerateSummaryJob from create events
    Queue::assertPushed(GenerateSummaryJob::class, 5);

    // Now run the backfill command — should dispatch 3 more (only for null summaries)
    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // 5 from create + 3 from backfill = 8
    Queue::assertPushed(GenerateSummaryJob::class, 8);
});

it('skips shares without commentary', function () {
    Queue::fake();

    Share::factory()->withoutSummary()->create(['commentary' => null]);
    Share::factory()->withoutSummary()->create(['commentary' => 'Has commentary']);

    // 1 GenerateSummaryJob from create events (null commentary share skips chain)
    Queue::assertPushed(GenerateSummaryJob::class, 1);

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // Only the share with commentary gets a backfill job: 1 from create + 1 from backfill = 2
    Queue::assertPushed(GenerateSummaryJob::class, 2);
});

it('does not dispatch PostToXJob from backfill', function () {
    Queue::fake();

    Share::factory()->withoutSummary()->create();

    // Trait dispatches PostToXJob as part of the chain on create
    $postToXCountBeforeBackfill = Queue::pushed(PostToXJob::class)->count();

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // Backfill should not have added any additional PostToXJob
    expect(Queue::pushed(PostToXJob::class)->count())->toBe($postToXCountBeforeBackfill);
});

it('reports zero jobs when all shares have summaries', function () {
    Queue::fake();

    Share::factory()->withSummary()->count(3)->create();

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful()
        ->expectsOutputToContain('0 job(s) dispatched');
});
