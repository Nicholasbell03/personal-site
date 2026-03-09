<?php

use App\Enums\SourceType;
use App\Exceptions\XCreditsDepletedException;
use App\Models\Share;
use App\Services\XPostingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

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

it('throws when successful x response is missing data id', function () {
    config([
        'services.x.api_key' => 'test-key',
        'services.x.api_secret' => 'test-secret',
        'services.x.access_token' => 'test-token',
        'services.x.access_token_secret' => 'test-token-secret',
    ]);

    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'data' => ['text' => 'posted'],
        ], 201),
    ]);

    $share = Share::factory()->make([
        'source_type' => SourceType::Webpage,
        'summary' => 'Ship small, iterate fast.',
        'url' => 'https://example.com/post',
    ]);

    $service = new XPostingService;

    expect(fn () => $service->postTweet($share))
        ->toThrow(\RuntimeException::class, 'malformed X API response payload');
});

it('throws XCreditsDepletedException on 402 response', function () {
    config([
        'services.x.api_key' => 'test-key',
        'services.x.api_secret' => 'test-secret',
        'services.x.access_token' => 'test-token',
        'services.x.access_token_secret' => 'test-token-secret',
    ]);

    Http::fake([
        'https://api.x.com/2/tweets' => Http::response([
            'title' => 'CreditsDepleted',
            'detail' => 'Your account does not have any credits.',
        ], 402),
    ]);

    $share = Share::factory()->make([
        'source_type' => SourceType::Webpage,
        'summary' => 'A great article.',
        'url' => 'https://example.com/post',
    ]);

    $service = new XPostingService;

    expect(fn () => $service->postTweet($share))
        ->toThrow(XCreditsDepletedException::class);
});
