<?php

use Illuminate\Support\Facades\Http;

it('reports valid when linkedin token works', function () {
    config(['services.linkedin.access_token' => 'valid-token']);

    Http::fake([
        'https://api.linkedin.com/v2/userinfo' => Http::response(['sub' => '123'], 200),
    ]);

    $this->artisan('linkedin:check-token')
        ->expectsOutput('LinkedIn token is valid.')
        ->assertSuccessful();
});

it('reports expired when linkedin returns 401', function () {
    config(['services.linkedin.access_token' => 'expired-token']);

    Http::fake([
        'https://api.linkedin.com/v2/userinfo' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->artisan('linkedin:check-token')
        ->expectsOutput('LinkedIn access token expired — refresh at https://www.linkedin.com/developers/')
        ->assertFailed();
});

it('warns when no linkedin token is configured', function () {
    config(['services.linkedin.access_token' => null]);

    $this->artisan('linkedin:check-token')
        ->expectsOutput('No LinkedIn token configured.')
        ->assertFailed();
});

it('returns 200 from cron route when token is valid', function () {
    config(['services.linkedin.access_token' => 'valid-token']);

    Http::fake([
        'https://api.linkedin.com/v2/userinfo' => Http::response(['sub' => '123'], 200),
    ]);

    $this->getJson('/api/check-linkedin-token')
        ->assertSuccessful()
        ->assertJson(['status' => 'ok']);
});

it('returns 503 from cron route when token is expired', function () {
    config(['services.linkedin.access_token' => 'expired-token']);

    Http::fake([
        'https://api.linkedin.com/v2/userinfo' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->getJson('/api/check-linkedin-token')
        ->assertServiceUnavailable()
        ->assertJson(['status' => 'error']);
});
