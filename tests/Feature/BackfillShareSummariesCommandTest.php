<?php

use App\Jobs\ProcessShareSummaryAndTweetJob;
use App\Models\Share;
use Illuminate\Support\Facades\Queue;

it('dispatches summary jobs for shares without summaries', function () {
    Queue::fake();

    // Create shares — each triggers a job via HasSummary trait
    Share::factory()->withoutSummary()->count(3)->create();
    Share::factory()->withSummary()->count(2)->create();

    // 5 jobs from create events
    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, 5);

    // Now run the backfill command — should dispatch 3 more (only for null summaries)
    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // 5 from create + 3 from backfill = 8
    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, 8);
});

it('skips shares without commentary', function () {
    Queue::fake();

    Share::factory()->withoutSummary()->create(['commentary' => null]);
    Share::factory()->withoutSummary()->create(['commentary' => 'Has commentary']);

    // 2 from create events
    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, 2);

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // Only the share with commentary gets a backfill job
    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, 3);
});

it('dispatches with skipXPosting flag', function () {
    Queue::fake();

    Share::factory()->withoutSummary()->create();

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful();

    // Find the job dispatched by the command (with skipXPosting: true)
    Queue::assertPushed(ProcessShareSummaryAndTweetJob::class, function ($job) {
        return $job->skipXPosting === true;
    });
});

it('reports zero jobs when all shares have summaries', function () {
    Queue::fake();

    Share::factory()->withSummary()->count(3)->create();

    $this->artisan('shares:backfill-summaries')
        ->assertSuccessful()
        ->expectsOutputToContain('0 job(s) dispatched');
});
