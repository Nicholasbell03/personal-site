<?php

use App\Enums\SourceType;
use App\Services\OpenGraphService;

beforeEach(function () {
    $this->service = new OpenGraphService;
});

it('detects youtube urls', function (string $url) {
    expect($this->service->detectSourceType($url))->toBe(SourceType::Youtube);
})->with([
    'standard' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'short' => 'https://youtu.be/dQw4w9WgXcQ',
    'mobile' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    'embed' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
]);

it('detects x/twitter urls', function (string $url) {
    expect($this->service->detectSourceType($url))->toBe(SourceType::XPost);
})->with([
    'x.com' => 'https://x.com/user/status/123456',
    'twitter.com' => 'https://twitter.com/user/status/123456',
    'mobile twitter' => 'https://mobile.twitter.com/user/status/123456',
]);

it('detects webpage urls', function (string $url) {
    expect($this->service->detectSourceType($url))->toBe(SourceType::Webpage);
})->with([
    'generic' => 'https://example.com/article',
    'laravel' => 'https://laravel.com/docs',
    'github' => 'https://github.com/laravel/framework',
]);

it('extracts youtube video id from various url formats', function (string $url, string $expectedId) {
    $result = $this->service->extractEmbedData($url, SourceType::Youtube);

    expect($result)->toBe(['video_id' => $expectedId]);
})->with([
    'standard' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'short' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'with params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120', 'dQw4w9WgXcQ'],
]);

it('extracts tweet id', function () {
    $result = $this->service->extractEmbedData(
        'https://x.com/taylorotwell/status/1234567890',
        SourceType::XPost,
    );

    expect($result)->toBe(['tweet_id' => '1234567890']);
});

it('returns null embed data for webpage', function () {
    $result = $this->service->extractEmbedData(
        'https://example.com/article',
        SourceType::Webpage,
    );

    expect($result)->toBeNull();
});

it('rejects private/loopback urls for ssrf protection', function (string $url) {
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
    $result = $this->service->fetch('not-a-url');

    expect($result['title'])->toBeNull()
        ->and($result['og_raw'])->toBeNull();
});
