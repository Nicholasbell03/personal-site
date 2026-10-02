<?php

use App\Services\OpenGraphService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

$ogPage = '<html><head><meta property="og:title" content="Public page"></head></html>';

it('follows a redirect to another public address', function () use ($ogPage) {
    Http::fake([
        'https://93.184.216.34/start' => Http::response('', 302, ['Location' => 'https://93.184.216.35/final']),
        'https://93.184.216.35/final' => Http::response($ogPage),
    ]);

    expect(app(OpenGraphService::class)->fetch('https://93.184.216.34/start')['title'])->toBe('Public page');
});

it('refuses to follow a redirect into an internal address', function (string $target) {
    Http::fake([
        'https://93.184.216.34/start' => Http::response('', 302, ['Location' => $target]),
        '*' => Http::response('secret'),
    ]);

    $result = app(OpenGraphService::class)->fetch('https://93.184.216.34/start');

    expect($result['title'])->toBeNull();
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request) => $request->url() === $target);
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:8080/admin',
    'private network' => 'http://10.0.0.5/',
    'carrier-grade NAT' => 'http://100.64.0.1/',
    'ipv6 loopback' => 'http://[::1]/',
    'non-http scheme' => 'file:///etc/passwd',
]);

it('refuses an internal address as the starting URL', function () {
    Http::fake();

    expect(app(OpenGraphService::class)->fetch('http://169.254.169.254/latest/meta-data/')['title'])->toBeNull();
    Http::assertNothingSent();
});

it('gives up after too many redirects', function () {
    Http::fake([
        'https://93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://93.184.216.34/again']),
    ]);

    expect(app(OpenGraphService::class)->fetch('https://93.184.216.34/start')['title'])->toBeNull();
    Http::assertSentCount(4);
});

it('resolves relative redirect locations against the current URL', function () use ($ogPage) {
    Http::fake([
        'https://93.184.216.34/a/start' => Http::response('', 301, ['Location' => '../final']),
        'https://93.184.216.34/final' => Http::response($ogPage),
    ]);

    expect(app(OpenGraphService::class)->fetch('https://93.184.216.34/a/start')['title'])->toBe('Public page');
});
