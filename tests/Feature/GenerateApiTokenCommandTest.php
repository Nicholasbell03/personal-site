<?php

use App\Models\User;

it('generates a token for a user by id', function () {
    $user = User::factory()->create();

    $this->artisan('app:generate-api-token', [
        '--user' => $user->id,
        '--name' => 'test-token',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain($user->name)
        ->expectsOutputToContain('test-token');

    expect($user->tokens)->toHaveCount(1);
    expect($user->tokens->first()->name)->toBe('test-token');
});

it('generates a token for a user by email', function () {
    $user = User::factory()->create(['email' => 'nick@example.com']);

    $this->artisan('app:generate-api-token', [
        '--user' => 'nick@example.com',
        '--name' => 'email-token',
    ])
        ->assertSuccessful();

    expect($user->tokens)->toHaveCount(1);
});

it('fails when user is not found', function () {
    $this->artisan('app:generate-api-token', [
        '--user' => '999',
        '--name' => 'test-token',
    ])
        ->assertFailed()
        ->expectsOutputToContain('User not found');
});

it('fails when required options are missing', function () {
    $this->artisan('app:generate-api-token')
        ->assertFailed()
        ->expectsOutputToContain('Both --user and --name options are required');
});
