<?php

use App\Agents\Tools\GetShares;
use App\Models\Share;
use Laravel\Ai\Tools\Request;

it('returns shares', function () {
    Share::factory()->create(['title' => 'GetShares Test Link']);

    $tool = new GetShares;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('GetShares Test Link');
});

it('respects limit parameter', function () {
    Share::factory()->count(5)->create();

    $tool = new GetShares;
    $result = json_decode($tool->handle(new Request(['limit' => 2])), true);

    expect($result)->toHaveCount(2);
});

it('returns message when no shares found', function () {
    $tool = new GetShares;
    $result = $tool->handle(new Request([]));

    expect($result)->toBe('No shared content found.');
});
