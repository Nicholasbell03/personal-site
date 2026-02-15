<?php

use App\Agents\Tools\GetCVDetail;
use App\Enums\UserContextKey;
use App\Models\UserContext;
use Laravel\Ai\Tools\Request;

it('returns cached CV detail', function () {
    UserContext::factory()->create([
        'key' => UserContextKey::CvDetailed,
        'value' => '# Nick Bell CV\n\nFull detailed CV content here.',
    ]);

    $tool = new GetCVDetail;
    $result = $tool->handle(new Request([]));

    expect($result)->toContain('Nick Bell CV');
});

it('returns unavailable message when no CV exists', function () {
    $tool = new GetCVDetail;
    $result = $tool->handle(new Request([]));

    expect($result)->toBe('Detailed CV information is not currently available.');
});
