<?php

use App\Models\Blog;

it('returns max three published blogs from featured endpoint', function () {
    Blog::factory()->published()->count(5)->create();

    $response = $this->getJson('/api/v1/blogs/featured');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('excludes drafts from featured endpoint', function () {
    Blog::factory()->published()->count(2)->create();
    Blog::factory()->draft()->count(3)->create();

    $response = $this->getJson('/api/v1/blogs/featured');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns correct fields from featured endpoint', function () {
    Blog::factory()->published()->create();

    $response = $this->getJson('/api/v1/blogs/featured');

    $response->assertOk()
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
});

it('orders featured endpoint by latest published', function () {
    $oldest = Blog::factory()->published()->create(['published_at' => now()->subDays(3)]);
    $newest = Blog::factory()->published()->create(['published_at' => now()]);
    $middle = Blog::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->getJson('/api/v1/blogs/featured');

    $response->assertOk();
    $data = $response->json('data');

    expect($data[0]['id'])->toBe($newest->id)
        ->and($data[1]['id'])->toBe($middle->id)
        ->and($data[2]['id'])->toBe($oldest->id);
});

it('returns paginated results from index endpoint', function () {
    Blog::factory()->published()->count(15)->create();

    $response = $this->getJson('/api/v1/blogs');

    $response->assertOk()
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
});

it('excludes drafts from index endpoint', function () {
    Blog::factory()->published()->count(3)->create();
    Blog::factory()->draft()->count(5)->create();

    $response = $this->getJson('/api/v1/blogs');

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    expect($response->json('meta.total'))->toBe(3);
});

it('returns correct pagination metadata from index endpoint', function () {
    Blog::factory()->published()->count(25)->create();

    $response = $this->getJson('/api/v1/blogs');

    $response->assertOk()
        ->assertJson([
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 25,
                'last_page' => 3,
            ],
        ]);
});

it('returns correct results for second page', function () {
    Blog::factory()->published()->count(15)->create();

    $response = $this->getJson('/api/v1/blogs?page=2');

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJson([
            'meta' => [
                'current_page' => 2,
            ],
        ]);
});

it('returns full blog by slug from show endpoint', function () {
    Blog::factory()->published()->create([
        'slug' => 'my-test-blog',
        'content' => '<p>Test content</p>',
    ]);

    $response = $this->getJson('/api/v1/blogs/my-test-blog');

    $response->assertOk()
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
});

it('returns 404 for non-existent slug', function () {
    $response = $this->getJson('/api/v1/blogs/non-existent-slug');

    $response->assertNotFound();
});

it('returns 404 for draft blog', function () {
    Blog::factory()->draft()->create(['slug' => 'draft-blog']);

    $response = $this->getJson('/api/v1/blogs/draft-blog');

    $response->assertNotFound();
});

it('calculates read time correctly', function () {
    $words = str_repeat('word ', 400);
    $blog = Blog::factory()->published()->create(['content' => $words]);

    $response = $this->getJson('/api/v1/blogs/'.$blog->slug);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'read_time' => 2,
            ],
        ]);
});

it('rounds up read time', function () {
    $words = str_repeat('word ', 201);
    $blog = Blog::factory()->published()->create(['content' => $words]);

    $response = $this->getJson('/api/v1/blogs/'.$blog->slug);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'read_time' => 2,
            ],
        ]);
});

it('returns zero read time for empty content', function () {
    $blog = Blog::factory()->published()->create(['content' => '']);

    $response = $this->getJson('/api/v1/blogs/'.$blog->slug);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'read_time' => 0,
            ],
        ]);
});
