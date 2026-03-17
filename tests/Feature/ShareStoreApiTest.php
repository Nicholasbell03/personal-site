<?php

use App\Models\User;
use App\Services\OpenGraphService;
use App\Services\SummaryService;

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

it('persists post_to_x when provided', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $mockService = Mockery::mock(OpenGraphService::class);
    $mockService->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'title' => 'Title',
            'description' => 'Description',
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
            'post_to_x' => false,
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('shares', [
        'url' => 'https://example.com/article',
        'post_to_x' => false,
    ]);
});

it('defaults post_to_x to true when not provided', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $mockService = Mockery::mock(OpenGraphService::class);
    $mockService->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'title' => 'Title',
            'description' => 'Description',
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
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('shares', [
        'url' => 'https://example.com/article',
        'post_to_x' => true,
    ]);
});

it('returns 201 with warnings when a post-creation job fails on sync queue', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $mockOg = Mockery::mock(OpenGraphService::class);
    $mockOg->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'title' => 'Test Title',
            'description' => 'Test description',
            'image' => null,
            'site_name' => null,
            'author' => null,
            'source_type' => \App\Enums\SourceType::Webpage,
            'embed_data' => null,
            'og_raw' => null,
        ]);
    $this->app->instance(OpenGraphService::class, $mockOg);

    // Mock SummaryService to throw, simulating sync queue job failure
    $this->mock(SummaryService::class, function ($mock) {
        $mock->shouldReceive('generate')
            ->andThrow(new \RuntimeException('AI service unavailable'));
    });

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shares', [
            'url' => 'https://example.com/article',
            'commentary' => 'My thoughts on this article',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('shares', [
        'url' => 'https://example.com/article',
        'commentary' => 'My thoughts on this article',
    ]);

    $response->assertJsonPath('meta.warnings.0', 'Summary generation failed.');
});
