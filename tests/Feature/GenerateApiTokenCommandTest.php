<?php

use App\Models\User;

it('generates a token for a user by id', function () {
    $user = User::factory()->create();

    $this->artisan('app:generate-api-token', [
        '--user' => $user->id,
        '--name' => 'test-token',
        '--ability' => ['shares:create'],
    ])
        ->assertSuccessful()
        ->expectsOutputToContain($user->name)
        ->expectsOutputToContain('test-token');

    $token = $user->tokens()->sole();

    expect($token->name)->toBe('test-token')
        ->and($token->abilities)->toBe(['shares:create'])
        ->and($token->expires_at->isBetween(now()->addDays(89), now()->addDays(91)))->toBeTrue();
});

it('generates a token for a user by email', function () {
    $user = User::factory()->create(['email' => 'nick@example.com']);

    $this->artisan('app:generate-api-token', [
        '--user' => 'nick@example.com',
        '--name' => 'email-token',
        '--ability' => ['blogs:write', 'media:upload'],
        '--days' => 30,
    ])
        ->assertSuccessful();

    expect($user->tokens)->toHaveCount(1);
});

it('fails when user is not found', function () {
    $this->artisan('app:generate-api-token', [
        '--user' => '999',
        '--name' => 'test-token',
        '--ability' => ['shares:create'],
    ])
        ->assertFailed()
        ->expectsOutputToContain('User not found');
});

it('fails when required options are missing', function () {
    $this->artisan('app:generate-api-token')
        ->assertFailed()
        ->expectsOutputToContain('Both --user and --name options are required');
});

it('refuses to create a token without a valid ability', function (array $abilities) {
    $user = User::factory()->create();

    $this->artisan('app:generate-api-token', [
        '--user' => $user->id,
        '--name' => 'test-token',
        '--ability' => $abilities,
    ])
        ->assertFailed()
        ->expectsOutputToContain('Pass at least one valid --ability');

    expect($user->tokens)->toHaveCount(0);
})->with([
    'none' => [[]],
    'wildcard' => [['*']],
    'unknown' => [['shares:create', 'admin']],
]);
