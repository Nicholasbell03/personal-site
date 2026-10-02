<?php

use App\Http\Middleware\ValidatePreviewToken;
use App\Models\Blog;
use App\Models\Project;

describe('blog preview', function () {
    it('returns draft blog with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog', [
            'X-Preview-Token' => ValidatePreviewToken::issue('blogs', 'draft-blog'),
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
            'X-Preview-Token' => ValidatePreviewToken::issue('blogs', 'published-blog'),
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
            'X-Preview-Token' => ValidatePreviewToken::issue('blogs', 'non-existent'),
        ]);

        $response->assertNotFound();
    });
});

describe('project preview', function () {
    it('returns draft project with valid token', function () {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project', [
            'X-Preview-Token' => ValidatePreviewToken::issue('projects', 'draft-project'),
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
            'X-Preview-Token' => ValidatePreviewToken::issue('projects', 'published-project'),
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
            'X-Preview-Token' => ValidatePreviewToken::issue('projects', 'non-existent'),
        ]);

        $response->assertNotFound();
    });
});

describe('preview token scoping', function () {
    beforeEach(function () {
        config(['app.preview_token' => 'test-preview-token']);
        Blog::factory()->draft()->create(['slug' => 'draft-blog']);
        Blog::factory()->draft()->create(['slug' => 'other-draft']);
        Project::factory()->draft()->create(['slug' => 'draft-blog']);
    });

    it('no longer accepts the raw PREVIEW_TOKEN secret', function () {
        $this->getJson('/api/v1/blogs/preview/draft-blog', ['X-Preview-Token' => 'test-preview-token'])
            ->assertForbidden();
    });

    it('only unlocks the item it was issued for', function () {
        $token = ValidatePreviewToken::issue('blogs', 'draft-blog');

        $this->getJson('/api/v1/blogs/preview/draft-blog', ['X-Preview-Token' => $token])->assertOk();
        $this->getJson('/api/v1/blogs/preview/other-draft', ['X-Preview-Token' => $token])->assertForbidden();
        $this->getJson('/api/v1/projects/preview/draft-blog', ['X-Preview-Token' => $token])->assertForbidden();
    });

    it('rejects an expired token', function () {
        $token = ValidatePreviewToken::issue('blogs', 'draft-blog', now()->subSecond()->getTimestamp());

        $this->getJson('/api/v1/blogs/preview/draft-blog', ['X-Preview-Token' => $token])->assertForbidden();
    });

    it('rejects a token whose expiry was tampered with', function () {
        [, $signature] = explode('.', ValidatePreviewToken::issue('blogs', 'draft-blog', now()->addMinute()->getTimestamp()));

        $this->getJson('/api/v1/blogs/preview/draft-blog', ['X-Preview-Token' => now()->addYear()->getTimestamp().'.'.$signature])
            ->assertForbidden();
    });

    it('stops working once PREVIEW_TOKEN is rotated', function () {
        $token = ValidatePreviewToken::issue('blogs', 'draft-blog');

        config(['app.preview_token' => 'rotated-secret']);

        $this->getJson('/api/v1/blogs/preview/draft-blog', ['X-Preview-Token' => $token])->assertForbidden();
    });

    it('expires a week after issue by default', function () {
        [$expiresAt] = explode('.', ValidatePreviewToken::issue('blogs', 'draft-blog'));

        expect((int) $expiresAt)->toBe(now()->getTimestamp() + ValidatePreviewToken::TTL_SECONDS);
    });
});
