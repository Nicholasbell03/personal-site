<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets the admin account into the panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful();
});

it('refuses any other user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('matches the admin email case-insensitively', function () {
    $user = User::factory()->create(['email' => strtoupper(config('app.admin_email'))]);

    expect($user->isAdmin())->toBeTrue();
});

it('refuses everyone when no admin email is configured', function () {
    $admin = User::factory()->admin()->create();

    config(['app.admin_email' => null]);

    expect($admin->isAdmin())->toBeFalse();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertForbidden();
});

it('keeps the log viewer closed to non-admins', function () {
    $this->actingAs(User::factory()->create())
        ->get('/log-viewer')
        ->assertForbidden();
});

it('serves the profile page for setting up two-factor authentication', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/profile')
        ->assertSuccessful()
        ->assertSee('authentication', false);
});
