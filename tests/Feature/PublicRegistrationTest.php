<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Every user can access the admin panel and mint API tokens, so users must only ever be
 * created by an admin or via Artisan. These guard against a public sign-up path returning.
 */
it('does not expose a public registration endpoint', function () {
    $this->postJson('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('does not register any registration route', function () {
    $registrationRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'register') || str_contains((string) $route->getName(), 'register'));

    expect($registrationRoutes)->toBeEmpty();
});

it('keeps the admin panel login available', function () {
    $this->get('/admin/login')->assertOk();
});
