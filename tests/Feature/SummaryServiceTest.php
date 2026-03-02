<?php

use App\Models\Share;
use App\Services\SummaryService;

it('returns null when commentary is empty', function () {
    $share = Share::factory()->make([
        'commentary' => null,
    ]);

    $service = new SummaryService;
    $result = $service->generate($share);

    expect($result)->toBeNull();
});

it('returns null when commentary is only whitespace', function () {
    $share = Share::factory()->make([
        'commentary' => '   ',
    ]);

    $service = new SummaryService;
    $result = $service->generate($share);

    expect($result)->toBeNull();
});

it('returns null when commentary is only HTML tags', function () {
    $share = Share::factory()->make([
        'commentary' => '<p></p><br>',
    ]);

    $service = new SummaryService;
    $result = $service->generate($share);

    expect($result)->toBeNull();
});
