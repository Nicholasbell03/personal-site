<?php

use App\Models\Blog;
use App\Models\Project;

describe('blog preview', function () {
    it('returns draft blog with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                ],
            ])
            ->assertJson([
                'data' => [
                    'slug' => 'draft-blog',
                ],
            ]);
    });

    it('returns published blog with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->published()->create(['slug' => 'published-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/published-blog', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'slug' => 'published-blog',
                ],
            ]);
    });

    it('rejects invalid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog', [
            'X-Preview-Token' => 'wrong-token',
        ]);

        $response->assertForbidden();
    });

    it('rejects missing token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog');

        $response->assertForbidden();
    });

    it('rejects when no token configured', function () {
        config(['app.preview_token' => null]);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog', [
            'X-Preview-Token' => 'any-token',
        ]);

        $response->assertForbidden();
    });

    it('returns 404 for non-existent slug', function () {
        config(['app.preview_token' => 'test-preview-token']);

        $response = $this->getJson('/api/v1/blogs/preview/non-existent', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertNotFound();
    });
});

describe('project preview', function () {
    it('returns draft project with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'slug' => 'draft-project',
                ],
            ]);
    });

    it('returns published project with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->published()->create(['slug' => 'published-project']);

        $response = $this->getJson('/api/v1/projects/preview/published-project', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'slug' => 'published-project',
                ],
            ]);
    });

    it('rejects invalid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project', [
            'X-Preview-Token' => 'wrong-token',
        ]);

        $response->assertForbidden();
    });

    it('rejects missing token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project');

        $response->assertForbidden();
    });

    it('rejects when no token configured', function () {
        config(['app.preview_token' => null]);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project', [
            'X-Preview-Token' => 'any-token',
        ]);

        $response->assertForbidden();
    });

    it('returns 404 for non-existent slug', function () {
        config(['app.preview_token' => 'test-preview-token']);

        $response = $this->getJson('/api/v1/projects/preview/non-existent', [
            'X-Preview-Token' => 'test-preview-token',
        ]);

        $response->assertNotFound();
    });
});
