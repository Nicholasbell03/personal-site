<?php

use App\Enums\SourceType;
use App\Services\OpenGraphService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(OpenGraphService::class);
});

it('extracts author from x.com url path', function () {
    Http::fake([
        'https://x.com/*' => Http::response('<html><head>
            <meta property="og:title" content="A tweet">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://x.com/elonmusk/status/1234567890');

    expect($result['author'])->toBe('@elonmusk')
        ->and($result['source_type'])->toBe(SourceType::XPost);
});

it('extracts author from twitter.com url path', function () {
    Http::fake([
        'https://twitter.com/*' => Http::response('<html><head>
            <meta property="og:title" content="A tweet">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://twitter.com/taylorotwell/status/9876543210');

    expect($result['author'])->toBe('@taylorotwell')
        ->and($result['source_type'])->toBe(SourceType::XPost);
});

it('extracts author from article:author meta tag', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><head>
            <meta property="og:title" content="Blog Post">
            <meta property="article:author" content="Jane Smith">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://example.com/blog/post');

    expect($result['author'])->toBe('Jane Smith')
        ->and($result['source_type'])->toBe(SourceType::Webpage);
});

it('returns null author when no author metadata is available', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><head>
            <meta property="og:title" content="Page Title">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://example.com/page');

    expect($result['author'])->toBeNull();
});

it('returns null author for x post with non-standard url path', function () {
    Http::fake([
        'https://x.com/*' => Http::response('<html><head>
            <meta property="og:title" content="X Page">
        </head></html>'),
    ]);

    $result = $this->service->fetch('https://x.com/explore');

    expect($result['author'])->toBeNull()
        ->and($result['source_type'])->toBe(SourceType::XPost);
});
