<?php

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
