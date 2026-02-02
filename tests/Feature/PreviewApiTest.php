<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_preview_returns_draft_blog_with_valid_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        $blog = Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog?token=test-preview-token');

        $response->assertStatus(200)
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
    }

    public function test_blog_preview_returns_published_blog_with_valid_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        $blog = Blog::factory()->published()->create(['slug' => 'published-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/published-blog?token=test-preview-token');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'published-blog',
                ],
            ]);
    }

    public function test_blog_preview_rejects_invalid_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog?token=wrong-token');

        $response->assertStatus(403);
    }

    public function test_blog_preview_rejects_missing_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog');

        $response->assertStatus(403);
    }

    public function test_blog_preview_rejects_when_no_token_configured(): void
    {
        config(['app.preview_token' => null]);

        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/v1/blogs/preview/draft-blog?token=any-token');

        $response->assertStatus(403);
    }

    public function test_blog_preview_returns_404_for_non_existent_slug(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        $response = $this->getJson('/api/v1/blogs/preview/non-existent?token=test-preview-token');

        $response->assertStatus(404);
    }

    public function test_project_preview_returns_draft_project_with_valid_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        $project = Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project?token=test-preview-token');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'draft-project',
                ],
            ]);
    }

    public function test_project_preview_rejects_invalid_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project?token=wrong-token');

        $response->assertStatus(403);
    }

    public function test_project_preview_rejects_missing_token(): void
    {
        config(['app.preview_token' => 'test-preview-token']);

        Project::factory()->draft()->create(['slug' => 'draft-project']);

        $response = $this->getJson('/api/v1/projects/preview/draft-project');

        $response->assertStatus(403);
    }
}
