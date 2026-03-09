<?php

use App\Enums\PublishStatus;
use App\Models\Blog;
use App\Models\User;

it('creates a draft blog with valid sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'My Test Blog Post',
            'content' => 'This is the content of my test blog post.',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'slug',
                'excerpt',
                'content',
                'featured_image',
                'meta_description',
                'published_at',
                'read_time',
            ],
            'admin_url',
        ]);

    $this->assertDatabaseHas('blogs', [
        'title' => 'My Test Blog Post',
        'status' => PublishStatus::Draft->value,
    ]);
});

it('always creates blogs in draft status', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Attempted Published Blog',
            'content' => 'Trying to publish directly.',
            'status' => 'published',
        ]);

    $response->assertCreated();

    $blog = Blog::where('title', 'Attempted Published Blog')->first();
    expect($blog->status)->toBe(PublishStatus::Draft);
    expect($blog->published_at)->toBeNull();
});

it('auto-generates slug from title', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'My Amazing Blog Post',
            'content' => 'Content here.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'my-amazing-blog-post');
});

it('accepts a custom slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'My Blog Post',
            'content' => 'Content here.',
            'slug' => 'custom-slug',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'custom-slug');
});

it('accepts optional fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Full Blog Post',
            'content' => 'Full content here.',
            'excerpt' => 'A short excerpt.',
            'meta_description' => 'SEO description.',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('blogs', [
        'title' => 'Full Blog Post',
        'excerpt' => 'A short excerpt.',
        'meta_description' => 'SEO description.',
    ]);
});

it('returns admin url in response', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Admin URL Test',
            'content' => 'Content.',
        ]);

    $response->assertCreated();

    $blog = Blog::where('title', 'Admin URL Test')->first();
    expect($response->json('admin_url'))->toContain('/admin/blogs/' . $blog->id . '/edit');
});

it('rejects unauthenticated store requests', function () {
    $response = $this->postJson('/api/v1/blogs', [
        'title' => 'Unauthorized Blog',
        'content' => 'Content.',
    ]);

    $response->assertUnauthorized();
});

it('validates title is required', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'content' => 'Content without a title.',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('validates content is required', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Title without content',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

it('validates slug uniqueness', function () {
    Blog::factory()->create(['slug' => 'existing-slug']);

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Duplicate Slug Blog',
            'content' => 'Content.',
            'slug' => 'existing-slug',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('calculates read time from content', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $content = implode(' ', array_fill(0, 400, 'word'));

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/blogs', [
            'title' => 'Long Blog Post',
            'content' => $content,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.read_time', 2);
});
