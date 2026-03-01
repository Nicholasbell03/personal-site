<?php

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use App\Models\Share;
use Illuminate\Support\Facades\Bus;

it('dispatches chained summary and x posting jobs when share is created', function () {
    Bus::fake();

    $share = Share::factory()->withoutSummary()->create(['commentary' => 'Some thoughts']);

    Bus::assertChained([
        GenerateSummaryJob::class,
        PostToXJob::class,
    ]);
});

it('dispatches chain even when share has summary', function () {
    Bus::fake();

    $share = Share::factory()->withSummary()->create(['commentary' => 'Some thoughts']);

    Bus::assertChained([
        GenerateSummaryJob::class,
        PostToXJob::class,
    ]);
});

it('does not dispatch chain when commentary is null', function () {
    Bus::fake();

    $share = Share::factory()->withoutCommentary()->create();

    Bus::assertNotDispatched(GenerateSummaryJob::class);
});
