<?php

use App\Agents\PortfolioAgent;

it('uses the configured provider', function () {
    config()->set('agent.portfolio.provider', 'openai');

    $agent = new PortfolioAgent;

    expect($agent->provider())->toBe('openai');
});

it('uses the configured model', function () {
    config()->set('agent.portfolio.model', 'gpt-4o');

    $agent = new PortfolioAgent;

    expect($agent->model())->toBe('gpt-4o');
});

it('uses the configured timeout', function () {
    config()->set('agent.portfolio.timeout', 30);

    $agent = new PortfolioAgent;

    expect($agent->timeout())->toBe(30);
});

it('defaults provider to gemini', function () {
    $agent = new PortfolioAgent;

    expect($agent->provider())->toBe('gemini');
});

it('defaults model to gemini-3-flash-preview', function () {
    $agent = new PortfolioAgent;

    expect($agent->model())->toBe('gemini-3-flash-preview');
});

it('defaults timeout to 15', function () {
    $agent = new PortfolioAgent;

    expect($agent->timeout())->toBe(15);
});
