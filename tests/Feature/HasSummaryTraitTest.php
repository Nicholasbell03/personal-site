<?php

use App\Jobs\GenerateSummaryJob;
use App\Jobs\PostToXJob;
use App\Models\Share;
use Illuminate\Support\Facades\Bus;

it('dispatches summary and x posting jobs independently when share is created', function () {
    Bus::fake();

    $share = Share::factory()->withoutSummary()->create(['commentary' => 'Some thoughts']);

    Bus::assertDispatched(GenerateSummaryJob::class);
    Bus::assertDispatched(PostToXJob::class);
});

it('dispatches both jobs even when share has summary', function () {
    Bus::fake();

    $share = Share::factory()->withSummary()->create(['commentary' => 'Some thoughts']);

    Bus::assertDispatched(GenerateSummaryJob::class);
    Bus::assertDispatched(PostToXJob::class);
});

it('dispatches PostToXJob with a delay', function () {
    Bus::fake();

    Share::factory()->withoutSummary()->create(['commentary' => 'Some thoughts']);

    Bus::assertDispatched(PostToXJob::class, function (PostToXJob $job) {
        return $job->delay !== null;
    });
});

it('does not dispatch jobs when commentary is null', function () {
    Bus::fake();

    $share = Share::factory()->withoutCommentary()->create();

    Bus::assertNotDispatched(GenerateSummaryJob::class);
    Bus::assertNotDispatched(PostToXJob::class);
});

it('does not dispatch jobs when commentary is whitespace only', function () {
    Bus::fake();

    Share::factory()->withoutSummary()->create(['commentary' => '   ']);

    Bus::assertNotDispatched(GenerateSummaryJob::class);
    Bus::assertNotDispatched(PostToXJob::class);
});

it('does not dispatch jobs when commentary has only html tags', function () {
    Bus::fake();

    Share::factory()->withoutSummary()->create(['commentary' => '<p></p><br>']);

    Bus::assertNotDispatched(GenerateSummaryJob::class);
    Bus::assertNotDispatched(PostToXJob::class);
});
