<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_endpoint_returns_max_three_published_blogs(): void
    {
        Blog::factory()->published()->count(5)->create();

        $response = $this->getJson('/api/blogs/featured');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_featured_endpoint_excludes_drafts(): void
    {
        Blog::factory()->published()->count(2)->create();
        Blog::factory()->draft()->count(3)->create();

        $response = $this->getJson('/api/blogs/featured');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_featured_endpoint_returns_correct_fields(): void
    {
        $blog = Blog::factory()->published()->create();

        $response = $this->getJson('/api/blogs/featured');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'featured_image',
                        'published_at',
                        'read_time',
                    ],
                ],
            ])
            ->assertJsonMissing(['content']);
    }

    public function test_featured_endpoint_orders_by_latest_published(): void
    {
        $oldest = Blog::factory()->published()->create(['published_at' => now()->subDays(3)]);
        $newest = Blog::factory()->published()->create(['published_at' => now()]);
        $middle = Blog::factory()->published()->create(['published_at' => now()->subDay()]);

        $response = $this->getJson('/api/blogs/featured');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($newest->id, $data[0]['id']);
        $this->assertEquals($middle->id, $data[1]['id']);
        $this->assertEquals($oldest->id, $data[2]['id']);
    }

    public function test_index_endpoint_returns_paginated_results(): void
    {
        Blog::factory()->published()->count(15)->create();

        $response = $this->getJson('/api/blogs');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_index_endpoint_excludes_drafts(): void
    {
        Blog::factory()->published()->count(3)->create();
        Blog::factory()->draft()->count(5)->create();

        $response = $this->getJson('/api/blogs');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_index_pagination_metadata_is_correct(): void
    {
        Blog::factory()->published()->count(25)->create();

        $response = $this->getJson('/api/blogs');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 25,
                    'last_page' => 3,
                ],
            ]);
    }

    public function test_index_second_page_returns_correct_results(): void
    {
        Blog::factory()->published()->count(15)->create();

        $response = $this->getJson('/api/blogs?page=2');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'current_page' => 2,
                ],
            ]);
    }

    public function test_show_endpoint_returns_full_blog_by_slug(): void
    {
        $blog = Blog::factory()->published()->create([
            'slug' => 'my-test-blog',
            'content' => '<p>Test content</p>',
        ]);

        $response = $this->getJson('/api/blogs/my-test-blog');

        $response->assertStatus(200)
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
            ])
            ->assertJson([
                'data' => [
                    'slug' => 'my-test-blog',
                    'content' => '<p>Test content</p>',
                ],
            ]);
    }

    public function test_show_endpoint_returns_404_for_non_existent_slug(): void
    {
        $response = $this->getJson('/api/blogs/non-existent-slug');

        $response->assertStatus(404);
    }

    public function test_show_endpoint_returns_404_for_draft_blog(): void
    {
        Blog::factory()->draft()->create(['slug' => 'draft-blog']);

        $response = $this->getJson('/api/blogs/draft-blog');

        $response->assertStatus(404);
    }

    public function test_read_time_calculation_is_correct(): void
    {
        $words = str_repeat('word ', 400);
        $blog = Blog::factory()->published()->create(['content' => $words]);

        $response = $this->getJson('/api/blogs/' . $blog->slug);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'read_time' => 2,
                ],
            ]);
    }

    public function test_read_time_rounds_up(): void
    {
        $words = str_repeat('word ', 201);
        $blog = Blog::factory()->published()->create(['content' => $words]);

        $response = $this->getJson('/api/blogs/' . $blog->slug);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'read_time' => 2,
                ],
            ]);
    }

    public function test_empty_content_has_zero_read_time(): void
    {
        $blog = Blog::factory()->published()->create(['content' => '']);

        $response = $this->getJson('/api/blogs/' . $blog->slug);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'read_time' => 0,
                ],
            ]);
    }
}
