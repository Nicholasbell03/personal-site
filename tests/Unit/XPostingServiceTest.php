<?php

use App\Enums\SourceType;
use App\Models\Share;
use App\Services\XPostingService;

it('composes tweet with summary and url for webpage shares', function () {
    $share = Share::factory()->make([
        'url' => 'https://example.com/article',
        'source_type' => SourceType::Webpage,
        'summary' => 'Great insights on testing.',
    ]);

    $service = new XPostingService;
    $tweet = $service->composeTweet($share);

    expect($tweet)->toBe("Great insights on testing.\n\nhttps://example.com/article");
});

it('composes tweet with summary and url for youtube shares', function () {
    $share = Share::factory()->youtube()->make([
        'summary' => 'Must-watch video on Laravel.',
    ]);

    $service = new XPostingService;
    $tweet = $service->composeTweet($share);

    expect($tweet)->toContain('Must-watch video on Laravel.')
        ->and($tweet)->toContain($share->url);
});

it('composes quote tweet with summary only for x post shares', function () {
    $share = Share::factory()->xPost()->make([
        'summary' => 'Spot on take about PHP.',
    ]);

    $service = new XPostingService;
    $tweet = $service->composeTweet($share);

    expect($tweet)->toBe('Spot on take about PHP.')
        ->and($tweet)->not->toContain($share->url);
});

it('truncates summary to respect tweet character limit for non-x-post shares', function () {
    $longSummary = str_repeat('a', 300);

    $share = Share::factory()->make([
        'url' => 'https://example.com/article',
        'source_type' => SourceType::Webpage,
        'summary' => $longSummary,
    ]);

    $service = new XPostingService;
    $tweet = $service->composeTweet($share);

    // Max summary: 280 - 23 (t.co) - 2 (\n\n) = 255 chars
    // + 2 (\n\n) + URL
    expect(mb_strlen($tweet))->toBeLessThanOrEqual(280 + strlen($share->url) - 23);
});
