<?php

use App\Agents\PortfolioAgent;
use Laravel\Ai\Enums\Lab;

it('returns the primary and fallback providers in failover order', function () {
    config()->set('agent.portfolio.provider', 'openai');
    config()->set('agent.portfolio.model', 'gpt-5.6-luna');
    config()->set('agent.portfolio.fallback_provider', 'gemini');
    config()->set('agent.portfolio.fallback_model', 'gemini-3.5-flash');

    $agent = new PortfolioAgent;

    expect($agent->provider())->toBe([
        'openai' => 'gpt-5.6-luna',
        'gemini' => 'gemini-3.5-flash',
    ]);
});

it('returns only the primary provider when no fallback is configured', function () {
    config()->set('agent.portfolio.provider', 'openai');
    config()->set('agent.portfolio.model', 'gpt-5.6-luna');
    config()->set('agent.portfolio.fallback_provider', null);

    $agent = new PortfolioAgent;

    expect($agent->provider())->toBe(['openai' => 'gpt-5.6-luna']);
});

it('ignores a fallback that duplicates the primary provider', function () {
    config()->set('agent.portfolio.provider', 'openai');
    config()->set('agent.portfolio.model', 'gpt-5.6-luna');
    config()->set('agent.portfolio.fallback_provider', 'openai');
    config()->set('agent.portfolio.fallback_model', 'gpt-5.6-sol');

    $agent = new PortfolioAgent;

    expect($agent->provider())->toBe(['openai' => 'gpt-5.6-luna']);
});

it('passes low reasoning effort to openai', function () {
    config()->set('agent.portfolio.openai_reasoning_effort', 'low');

    $agent = new PortfolioAgent;

    expect($agent->providerOptions(Lab::OpenAI))->toBe([
        'reasoning' => ['effort' => 'low'],
    ]);
    expect($agent->providerOptions('openai'))->toBe([
        'reasoning' => ['effort' => 'low'],
    ]);
});

it('passes low thinking level to gemini', function () {
    config()->set('agent.portfolio.gemini_thinking_level', 'low');

    $agent = new PortfolioAgent;

    expect($agent->providerOptions(Lab::Gemini))->toBe([
        'thinkingConfig' => ['thinkingLevel' => 'low'],
    ]);
});

it('returns no provider options for other providers', function () {
    $agent = new PortfolioAgent;

    expect($agent->providerOptions('anthropic'))->toBe([]);
});

it('uses the configured timeout', function () {
    config()->set('agent.portfolio.timeout', 30);

    $agent = new PortfolioAgent;

    expect($agent->timeout())->toBe(30);
});

it('defaults timeout to 15', function () {
    $agent = new PortfolioAgent;

    expect($agent->timeout())->toBe(15);
});
