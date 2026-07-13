<?php

use App\Enums\SourceType;
use App\Models\Share;
use App\Services\OpenGraphService;
use Illuminate\Support\Facades\Http;

const SYNDICATION_URL = 'https://cdn.syndication.twimg.com/*';

it('stores the syndication payload in embed_data when fetching an x_post url', function () {
    Http::fake([
        SYNDICATION_URL => Http::response([
            '__typename' => 'Tweet',
            'id_str' => '123456789',
            'text' => 'Hello world',
            'user' => ['name' => 'Test User', 'screen_name' => 'test'],
        ]),
        '*' => Http::response('<html><head><meta property="og:title" content="Post"/></head></html>'),
    ]);

    $data = app(OpenGraphService::class)->fetch('https://x.com/test/status/123456789');

    expect($data['source_type'])->toBe(SourceType::XPost)
        ->and($data['embed_data']['tweet_id'])->toBe('123456789')
        ->and($data['embed_data']['tweet']['text'])->toBe('Hello world');
});

it('degrades to tweet_id-only embed data when the tweet is tombstoned', function () {
    Http::fake([
        SYNDICATION_URL => Http::response(['__typename' => 'TweetTombstone']),
        '*' => Http::response('<html></html>'),
    ]);

    $data = app(OpenGraphService::class)->fetch('https://x.com/test/status/123456789');

    expect($data['embed_data'])->toBe(['tweet_id' => '123456789']);
});

it('degrades to tweet_id-only embed data when the syndication request fails', function () {
    Http::fake([
        SYNDICATION_URL => Http::response(null, 500),
        '*' => Http::response('<html></html>'),
    ]);

    $data = app(OpenGraphService::class)->fetch('https://x.com/test/status/123456789');

    expect($data['embed_data'])->toBe(['tweet_id' => '123456789']);
});

it('backfills the tweet payload for x_post shares missing it', function () {
    Http::fake([
        SYNDICATION_URL => Http::response([
            '__typename' => 'Tweet',
            'text' => 'Backfilled tweet',
        ]),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://x.com/test/status/987654321',
        'source_type' => SourceType::XPost,
        'embed_data' => ['tweet_id' => '987654321'],
    ]);

    $alreadyStored = Share::factory()->create([
        'url' => 'https://x.com/test/status/111',
        'source_type' => SourceType::XPost,
        'embed_data' => ['tweet_id' => '111', 'tweet' => ['text' => 'existing']],
    ]);

    $this->artisan('shares:backfill-tweet-data')->assertSuccessful();

    expect($share->refresh()->embed_data['tweet']['text'])->toBe('Backfilled tweet')
        ->and($alreadyStored->refresh()->embed_data['tweet']['text'])->toBe('existing');

    // Only the share missing a payload triggers a syndication request
    // (other requests here come from unrelated share-creation side effects).
    $syndicationRequests = Http::recorded(
        fn ($request) => str_contains($request->url(), 'cdn.syndication.twimg.com'),
    );
    expect($syndicationRequests)->toHaveCount(1);
});
