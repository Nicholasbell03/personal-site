<?php

use App\Models\User;

it('hides the api docs from guests', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'ui' => ['/docs/api'],
    'openapi json' => ['/docs/api.json'],
]);

it('redirects guest browsers to the admin login', function () {
    $this->get('/docs/api')->assertRedirect(route('filament.admin.auth.login'));
});

it('shows the docs ui to a logged-in admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/docs/api')
        ->assertOk();
});

it('serves the openapi document to a sanctum token', function () {
    $token = User::factory()->create()->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer');

    expect($response->json('paths'))->toHaveKeys(['/v1/media', '/v1/blogs', '/v1/blogs/{blog}'])
        ->and($response->json('paths./v1/media.post.security'))->toBeNull()
        ->and($response->json('paths./v1/blogs.get.security'))->toBe([])
        ->and($response->json('paths./v1/blogs/{blog}.patch.responses'))->toHaveKey('409');
});

it('keeps returning 401 rather than redirecting for api requests without a json accept header', function () {
    $this->post('/api/v1/media')->assertUnauthorized();
});
