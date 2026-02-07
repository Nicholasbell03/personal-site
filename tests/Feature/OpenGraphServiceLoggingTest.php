<?php

use App\Enums\SourceType;
use App\Models\Share;
use App\Services\OpenGraphService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->service = app(OpenGraphService::class);
});

it('rejects private/loopback urls for ssrf protection', function (string $url) {
    Log::spy();

    $result = $this->service->fetch($url);

    expect($result['title'])->toBeNull()
        ->and($result['og_raw'])->toBeNull();
})->with([
    'localhost' => 'http://localhost/admin',
    'loopback' => 'http://127.0.0.1/secret',
    'private class A' => 'http://10.0.0.1/internal',
    'private class C' => 'http://192.168.1.1/admin',
    'ftp scheme' => 'ftp://example.com/file',
]);

it('rejects urls with no host', function () {
    Log::spy();

    $result = $this->service->fetch('not-a-url');

    expect($result['title'])->toBeNull()
        ->and($result['og_raw'])->toBeNull();
});

it('logs warning when url fails safety check', function () {
    Log::spy();

    $this->service->fetch('http://127.0.0.1/secret');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'safety check'))
        ->once();
});

it('logs warning on non-successful http response', function () {
    Log::spy();
    Http::fake(['https://example.com/*' => Http::response('', 404)]);

    $result = $this->service->fetch('https://example.com/missing-page');

    expect($result['title'])->toBeNull()
        ->and($result['og_raw'])->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'non-successful'))
        ->once();
});

it('logs error on exception during fetch', function () {
    Log::spy();
    Http::fake(['https://example.com/*' => fn () => throw new \RuntimeException('Connection timed out')]);

    $result = $this->service->fetch('https://example.com/article');

    expect($result['title'])->toBeNull()
        ->and($result['og_raw'])->toBeNull();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => str_contains($message, 'exception'))
        ->once();
});

it('refreshMetadata updates share model with fetched OG data', function () {
    Http::fake([
        'https://example.com/article' => Http::response('<html><head>
            <meta property="og:title" content="Fresh Title">
            <meta property="og:description" content="Fresh description">
            <meta property="og:image" content="https://example.com/new-image.jpg">
            <meta property="og:site_name" content="Example Site">
        </head></html>'),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://example.com/article',
        'title' => 'Old Title',
        'description' => 'Old description',
        'image_url' => null,
        'site_name' => null,
    ]);

    $result = $this->service->refreshMetadata($share);

    expect($result->title)->toBe('Fresh Title')
        ->and($result->description)->toBe('Fresh description')
        ->and($result->image_url)->toBe('https://example.com/new-image.jpg')
        ->and($result->site_name)->toBe('Example Site')
        ->and($result->source_type)->toBe(SourceType::Webpage);

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'title' => 'Fresh Title',
        'description' => 'Fresh description',
        'image_url' => 'https://example.com/new-image.jpg',
    ]);
});

it('refreshMetadata preserves existing fields when OG data returns empty strings', function () {
    Http::fake([
        'https://example.com/empty-og' => Http::response('<html><head>
            <meta property="og:title" content="">
            <meta property="og:description" content="">
        </head></html>'),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://example.com/empty-og',
        'title' => 'Keep This Title',
        'description' => 'Keep this description',
    ]);

    $result = $this->service->refreshMetadata($share);

    expect($result->title)->toBe('Keep This Title')
        ->and($result->description)->toBe('Keep this description');
});

it('refreshMetadata preserves existing fields when OG data returns nulls', function () {
    Http::fake([
        'https://example.com/no-og' => Http::response('<html><head></head></html>'),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://example.com/no-og',
        'title' => 'Keep This Title',
        'description' => 'Keep this description',
    ]);

    $result = $this->service->refreshMetadata($share);

    expect($result->title)->toBe('Keep This Title')
        ->and($result->description)->toBe('Keep this description');
});

it('refreshMetadata detects source type for youtube urls', function () {
    Http::fake([
        'https://www.youtube.com/*' => Http::response('<html><head>
            <meta property="og:title" content="Cool Video">
        </head></html>'),
    ]);

    $share = Share::factory()->create([
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_type' => SourceType::Webpage,
    ]);

    $result = $this->service->refreshMetadata($share);

    expect($result->source_type)->toBe(SourceType::Youtube)
        ->and($result->embed_data)->toBe(['video_id' => 'dQw4w9WgXcQ']);
});
