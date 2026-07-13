<?php

use Illuminate\Support\Facades\Http;

function fakeSitemap(array $urls): string
{
    $locs = implode('', array_map(fn ($u) => "<url><loc>{$u}</loc></url>", $urls));

    return '<?xml version="1.0" encoding="UTF-8"?><urlset>'.$locs.'</urlset>';
}

beforeEach(function () {
    config(['app.frontend_url' => 'https://frontend.test']);
});

it('warms every url in the sitemap', function () {
    Http::fake([
        'https://frontend.test/sitemap.xml' => Http::response(fakeSitemap([
            'https://frontend.test/',
            'https://frontend.test/blog/post-one',
        ])),
        'https://frontend.test/*' => Http::response('ok'),
    ]);

    $this->artisan('frontend:warm-pages')
        ->expectsOutputToContain('Warmed 2/2 pages.')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://frontend.test/blog/post-one');
});

it('fails when the sitemap cannot be fetched', function () {
    Http::fake([
        'https://frontend.test/sitemap.xml' => Http::response(null, 500),
    ]);

    $this->artisan('frontend:warm-pages')->assertFailed();
});

it('fails when too many pages fail to warm', function () {
    Http::fake([
        'https://frontend.test/sitemap.xml' => Http::response(fakeSitemap([
            'https://frontend.test/',
            'https://frontend.test/blog/post-one',
        ])),
        'https://frontend.test/' => Http::response('ok'),
        'https://frontend.test/*' => Http::response(null, 500),
    ]);

    $this->artisan('frontend:warm-pages')->assertFailed();
});

it('exposes warming via the api route with cronjob-friendly statuses', function () {
    Http::fake([
        'https://frontend.test/sitemap.xml' => Http::response(fakeSitemap(['https://frontend.test/'])),
        'https://frontend.test/*' => Http::response('ok'),
    ]);

    $this->getJson('/api/warm-frontend')->assertOk()->assertJsonPath('status', 'ok');
});

it('returns 503 from the api route when warming fails', function () {
    Http::fake([
        'https://frontend.test/sitemap.xml' => Http::response(null, 500),
    ]);

    $this->getJson('/api/warm-frontend')->assertStatus(503);
});
