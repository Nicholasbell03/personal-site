<?php

use App\Enums\PublishStatus;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;

it('creates a draft project with valid sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'My Test Project',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'slug',
                'description',
                'long_description',
                'featured_image',
                'project_url',
                'github_url',
                'technologies',
                'published_at',
            ],
            'admin_url',
        ]);

    $this->assertDatabaseHas('projects', [
        'title' => 'My Test Project',
        'status' => PublishStatus::Draft->value,
    ]);
});

it('always creates projects in draft status', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Attempted Published Project',
            'status' => 'published',
        ]);

    $response->assertCreated();

    $project = Project::where('title', 'Attempted Published Project')->first();
    expect($project->status)->toBe(PublishStatus::Draft);
    expect($project->published_at)->toBeNull();
});

it('auto-generates slug from title', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'My Amazing Project',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'my-amazing-project');
});

it('accepts optional fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Full Project',
            'description' => 'Short description.',
            'long_description' => 'Detailed project description.',
            'project_url' => 'https://example.com',
            'github_url' => 'https://github.com/example/project',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('projects', [
        'title' => 'Full Project',
        'description' => 'Short description.',
        'long_description' => 'Detailed project description.',
        'project_url' => 'https://example.com',
        'github_url' => 'https://github.com/example/project',
    ]);
});

it('attaches technologies by id', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $tech1 = Technology::factory()->create(['name' => 'Laravel']);
    $tech2 = Technology::factory()->create(['name' => 'React']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Tech Project',
            'technologies' => [$tech1->id, $tech2->id],
        ]);

    $response->assertCreated();

    $project = Project::where('title', 'Tech Project')->first();
    expect($project->technologies)->toHaveCount(2);
    expect($project->technologies->pluck('name')->toArray())->toContain('Laravel', 'React');
});

it('returns technologies in response', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $tech = Technology::factory()->create(['name' => 'PHP']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Tech Response Project',
            'technologies' => [$tech->id],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.technologies', ['PHP']);
});

it('returns admin url in response', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Admin URL Test',
        ]);

    $response->assertCreated();

    $project = Project::where('title', 'Admin URL Test')->first();
    expect($response->json('admin_url'))->toContain('/admin/projects/' . $project->id . '/edit');
});

it('rejects unauthenticated store requests', function () {
    $response = $this->postJson('/api/v1/projects', [
        'title' => 'Unauthorized Project',
    ]);

    $response->assertUnauthorized();
});

it('validates title is required', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('validates technology ids exist', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Bad Tech Project',
            'technologies' => [999, 998],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['technologies.0', 'technologies.1']);
});

it('validates url format for project_url', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/projects', [
            'title' => 'Bad URL Project',
            'project_url' => 'not-a-url',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_url']);
});
