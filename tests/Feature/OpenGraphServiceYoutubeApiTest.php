<?php

use App\Enums\SourceType;
use App\Models\Share;
use App\Services\OpenGraphService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->service = app(OpenGraphService::class);
});

it('fetches metadata from youtube api when api key is configured', function () {
    Log::spy();
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'Amazing Video',
                    'description' => 'A great video about testing',
                    'channelTitle' => 'Test Channel',
                    'thumbnails' => [
                        'default' => ['url' => 'https://i.ytimg.com/vi/abc123/default.jpg'],
                        'high' => ['url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg'],
                        'maxres' => ['url' => 'https://i.ytimg.com/vi/abc123/maxresdefault.jpg'],
                    ],
                ],
            ]],
        ]),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['title'])->toBe('Amazing Video')
        ->and($result['description'])->toBe('A great video about testing')
        ->and($result['image'])->toBe('https://i.ytimg.com/vi/abc123/maxresdefault.jpg')
        ->and($result['site_name'])->toBe('YouTube')
        ->and($result['author'])->toBe('Test Channel')
        ->and($result['source_type'])->toBe(SourceType::Youtube)
        ->and($result['embed_data'])->toBe(['video_id' => 'dQw4w9WgXcQ'])
        ->and($result['og_raw'])->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'googleapis.com/youtube/v3/videos')
        && $request['id'] === 'dQw4w9WgXcQ'
        && $request['key'] === 'test-api-key'
    );

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => str_contains($message, 'YouTube API metadata fetched'))
        ->once();
});

it('falls back to og scraping when youtube api key is not configured', function () {
    config(['services.google.youtube_api_key' => null]);

    Http::fake([
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="OG Fallback Title">
            <meta property="og:description" content="OG Fallback Description">
            <meta property="og:image" content="https://i.ytimg.com/vi/abc/fallback.jpg">
            <meta property="og:site_name" content="YouTube">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['title'])->toBe('OG Fallback Title')
        ->and($result['description'])->toBe('OG Fallback Description')
        ->and($result['source_type'])->toBe(SourceType::Youtube)
        ->and($result['embed_data'])->toBe(['video_id' => 'dQw4w9WgXcQ'])
        ->and($result['og_raw'])->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'youtube.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
});

it('falls back to og scraping when youtube api returns error', function () {
    Log::spy();
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response(['error' => ['message' => 'Forbidden']], 403),
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="OG Fallback Title">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['title'])->toBe('OG Fallback Title')
        ->and($result['source_type'])->toBe(SourceType::Youtube);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'YouTube API request failed'))
        ->once();
});

it('falls back to og scraping when youtube api returns empty items', function () {
    Log::spy();
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response(['items' => []]),
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="OG Fallback Title">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['title'])->toBe('OG Fallback Title')
        ->and($result['source_type'])->toBe(SourceType::Youtube);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'no items'))
        ->once();
});

it('falls back to og scraping when youtube api throws exception', function () {
    Log::spy();
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => fn () => throw new \RuntimeException('Connection timed out'),
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="OG Fallback Title">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['title'])->toBe('OG Fallback Title')
        ->and($result['source_type'])->toBe(SourceType::Youtube);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_contains($message, 'YouTube API exception'))
        ->once();
});

it('selects the best available thumbnail by priority', function (array $thumbnails, string $expectedUrl) {
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'Test Video',
                    'description' => 'Test',
                    'thumbnails' => $thumbnails,
                ],
            ]],
        ]),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($result['image'])->toBe($expectedUrl);
})->with([
    'maxres preferred over all' => [
        [
            'default' => ['url' => 'https://i.ytimg.com/default.jpg'],
            'medium' => ['url' => 'https://i.ytimg.com/medium.jpg'],
            'high' => ['url' => 'https://i.ytimg.com/high.jpg'],
            'standard' => ['url' => 'https://i.ytimg.com/standard.jpg'],
            'maxres' => ['url' => 'https://i.ytimg.com/maxres.jpg'],
        ],
        'https://i.ytimg.com/maxres.jpg',
    ],
    'standard when no maxres' => [
        [
            'default' => ['url' => 'https://i.ytimg.com/default.jpg'],
            'high' => ['url' => 'https://i.ytimg.com/high.jpg'],
            'standard' => ['url' => 'https://i.ytimg.com/standard.jpg'],
        ],
        'https://i.ytimg.com/standard.jpg',
    ],
    'high when no maxres or standard' => [
        [
            'default' => ['url' => 'https://i.ytimg.com/default.jpg'],
            'high' => ['url' => 'https://i.ytimg.com/high.jpg'],
        ],
        'https://i.ytimg.com/high.jpg',
    ],
    'medium when only medium and default' => [
        [
            'default' => ['url' => 'https://i.ytimg.com/default.jpg'],
            'medium' => ['url' => 'https://i.ytimg.com/medium.jpg'],
        ],
        'https://i.ytimg.com/medium.jpg',
    ],
    'default as last resort' => [
        [
            'default' => ['url' => 'https://i.ytimg.com/default.jpg'],
        ],
        'https://i.ytimg.com/default.jpg',
    ],
]);

it('refreshMetadata uses youtube api for youtube urls', function () {
    Log::spy();
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'API Video Title',
                    'description' => 'API video description',
                    'thumbnails' => [
                        'high' => ['url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg'],
                    ],
                ],
            ]],
        ]),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Old Title',
        'source_type' => SourceType::Webpage,
    ]);

    $result = $this->service->refreshMetadata($share);

    expect($result->title)->toBe('API Video Title')
        ->and($result->description)->toBe('API video description')
        ->and($result->image_url)->toBe('https://i.ytimg.com/vi/abc123/hqdefault.jpg')
        ->and($result->site_name)->toBe('YouTube')
        ->and($result->source_type)->toBe(SourceType::Youtube)
        ->and($result->embed_data)->toBe(['video_id' => 'dQw4w9WgXcQ']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'youtube.com/watch'));
});

it('does not call youtube api for non-youtube urls', function () {
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'https://example.com/*' => Http::response('<html><head>
            <meta property="og:title" content="Example Page">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://example.com/article');

    expect($result['title'])->toBe('Example Page')
        ->and($result['source_type'])->toBe(SourceType::Webpage);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
});

it('does not call youtube api when video id cannot be extracted', function () {
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="YouTube Channel">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://www.youtube.com/channel/UCsomechannel');

    expect($result['title'])->toBe('YouTube Channel')
        ->and($result['source_type'])->toBe(SourceType::Youtube)
        ->and($result['embed_data'])->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
});

it('works with youtu.be short urls via youtube api', function () {
    config(['services.google.youtube_api_key' => 'test-api-key']);

    Http::fake([
        'googleapis.com/*' => Http::response([
            'items' => [[
                'snippet' => [
                    'title' => 'Short URL Video',
                    'description' => 'Description',
                    'thumbnails' => [
                        'high' => ['url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg'],
                    ],
                ],
            ]],
        ]),
    ]);

    $result = $this->service->fetch('https://youtu.be/dQw4w9WgXcQ');

    expect($result['title'])->toBe('Short URL Video')
        ->and($result['source_type'])->toBe(SourceType::Youtube)
        ->and($result['embed_data'])->toBe(['video_id' => 'dQw4w9WgXcQ']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
});
