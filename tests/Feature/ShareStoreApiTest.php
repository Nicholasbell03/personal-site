<?php

use App\Models\User;
use App\Services\OpenGraphService;

it('creates a share with valid sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $mockService = Mockery::mock(OpenGraphService::class);
    $mockService->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'title' => 'Fetched Title',
            'description' => 'Fetched description',
            'image' => 'https://example.com/image.jpg',
            'site_name' => 'Example',
            'author' => 'John Doe',
            'source_type' => \App\Enums\SourceType::Webpage,
            'embed_data' => null,
            'og_raw' => ['og:title' => 'Fetched Title'],
        ]);
    $this->app->instance(OpenGraphService::class, $mockService);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shares', [
            'url' => 'https://example.com/article',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'url',
                'source_type',
                'title',
                'slug',
            ],
        ]);

    $this->assertDatabaseHas('shares', [
        'url' => 'https://example.com/article',
        'title' => 'Fetched Title',
    ]);
});

it('rejects unauthenticated store requests', function () {
    $response = $this->postJson('/api/v1/shares', [
        'url' => 'https://example.com/article',
    ]);

    $response->assertUnauthorized();
});

it('validates url is required for store', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shares', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('validates url format for store', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shares', [
            'url' => 'not-a-url',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('uses user-provided title over OG title', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $mockService = Mockery::mock(OpenGraphService::class);
    $mockService->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'title' => 'OG Title',
            'description' => 'OG description',
            'image' => null,
            'site_name' => null,
            'author' => null,
            'source_type' => \App\Enums\SourceType::Webpage,
            'embed_data' => null,
            'og_raw' => null,
        ]);
    $this->app->instance(OpenGraphService::class, $mockService);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shares', [
            'url' => 'https://example.com/article',
            'title' => 'My Custom Title',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('shares', [
        'url' => 'https://example.com/article',
        'title' => 'My Custom Title',
    ]);
});
