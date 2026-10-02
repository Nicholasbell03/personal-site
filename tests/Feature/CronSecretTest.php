<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::shouldReceive('call')->never();
});

it('refuses cron endpoints without the secret header', function (string $uri) {
    $this->getJson($uri)->assertForbidden();
})->with(['/api/warm-cache', '/api/warm-frontend', '/api/check-linkedin-token']);

it('refuses a wrong cron secret', function (string $uri) {
    $this->withHeader('X-Cron-Secret', 'not-the-secret')->getJson($uri)->assertForbidden();
})->with(['/api/warm-cache', '/api/warm-frontend', '/api/check-linkedin-token']);

it('refuses everything when no cron secret is configured', function () {
    config(['app.cron_secret' => null]);

    $this->withHeader('X-Cron-Secret', '')->getJson('/api/warm-cache')->assertForbidden();
});
