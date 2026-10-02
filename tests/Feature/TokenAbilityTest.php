<?php

use App\Models\Blog;
use App\Models\User;

it('rejects a token used outside its scope', function (string $method, string $uri) {
    $token = User::factory()->create()->createToken('shares-only', ['shares:create'])->plainTextToken;
    $blog = Blog::factory()->create();

    $this->withToken($token)
        ->json($method, str_replace('{blog}', (string) $blog->id, $uri), [])
        ->assertForbidden();
})->with([
    'create blog' => ['POST', '/api/v1/blogs'],
    'update blog' => ['PATCH', '/api/v1/blogs/{blog}'],
    'upload media' => ['POST', '/api/v1/media'],
    'create project' => ['POST', '/api/v1/projects'],
]);

it('lets a scoped token reach its own route', function () {
    $token = User::factory()->create()->createToken('blogs', ['blogs:write'])->plainTextToken;

    // Passes the ability check and fails validation instead.
    $this->withToken($token)
        ->postJson('/api/v1/blogs', [])
        ->assertUnprocessable();
});

it('still accepts legacy wildcard tokens until they are revoked', function () {
    $token = User::factory()->create()->createToken('legacy')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/shares', [])
        ->assertUnprocessable();
});

it('rejects an expired token', function () {
    $token = User::factory()->create()->createToken('old', ['shares:create'], now()->subMinute())->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/shares', [])
        ->assertUnauthorized();
});
