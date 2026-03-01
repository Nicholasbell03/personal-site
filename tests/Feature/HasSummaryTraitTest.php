<?php

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use App\Models\Share;
use Illuminate\Support\Facades\Bus;

it('dispatches chained summary and x posting jobs when share is created', function () {
    Bus::fake();

    $share = Share::factory()->withoutSummary()->create();

    Bus::assertChained([
        GenerateSummaryJob::class,
        PostToXJob::class,
    ]);
});

it('dispatches chain even when share has summary', function () {
    Bus::fake();

    $share = Share::factory()->withSummary()->create();

    Bus::assertChained([
        GenerateSummaryJob::class,
        PostToXJob::class,
    ]);
});
