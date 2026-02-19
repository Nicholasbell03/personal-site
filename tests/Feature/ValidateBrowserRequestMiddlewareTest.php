<?php

use App\Agents\PortfolioAgent;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('chat.user_id');

    config(['cors.allowed_origins' => ['http://localhost:5173']]);
});

it('rejects requests with missing Origin header', function () {
    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'], [
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'cors',
    ])->assertForbidden();
});

it('rejects requests with wrong Origin header', function () {
    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'], [
        'Origin' => 'https://evil.com',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'cors',
    ])->assertForbidden();
});

it('rejects requests with missing Sec-Fetch-Site header', function () {
    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'], [
        'Origin' => 'http://localhost:5173',
        'Sec-Fetch-Mode' => 'cors',
    ])->assertForbidden();
});

it('rejects requests with wrong Sec-Fetch-Mode header', function () {
    PortfolioAgent::fake(['Response']);

    $this->postJson('/api/v1/chat', ['message' => 'Hello'], [
        'Origin' => 'http://localhost:5173',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'navigate',
    ])->assertForbidden();
});

it('allows any origin when allowed_origins contains wildcard', function () {
    config(['cors.allowed_origins' => ['*']]);

    PortfolioAgent::fake(['Response']);

    $this->post('/api/v1/chat', ['message' => 'Hello'], [
        'Accept' => 'application/json',
        'Origin' => 'https://any-site.example.com',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'cors',
    ])->assertOk();
});

it('allows requests with valid Origin and Sec-Fetch headers', function () {
    PortfolioAgent::fake(['Response']);

    $this->post('/api/v1/chat', ['message' => 'Hello'], [
        'Accept' => 'application/json',
        'Origin' => 'http://localhost:5173',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-Fetch-Mode' => 'cors',
    ])->assertOk();
});
