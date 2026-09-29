<?php

use App\Enums\PublishStatus;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.default' => 'r2',
        'filament.default_filesystem_disk' => 'r2',
    ]);
    Storage::fake('r2', ['url' => 'https://assets.nickbell.dev']);

    $this->token = User::factory()->create()->createToken('test')->plainTextToken;
});

it('updates a draft blog', function () {
    $blog = Blog::factory()->draft()->create();
    Storage::disk('r2')->put('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png', 'image');

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", [
            'title' => 'Revised Title',
            'slug' => 'revised-title',
            'excerpt' => 'Revised excerpt.',
            'content' => '<p>Revised</p><img src="https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png">',
            'meta_description' => 'Revised meta.',
            'featured_image' => 'blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png',
        ]);

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
            'admin_url',
        ])
        ->assertJsonPath('data.title', 'Revised Title')
        ->assertJsonPath('data.slug', 'revised-title')
        ->assertJsonPath('data.featured_image', 'https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png');

    expect($response->json('admin_url'))->toContain("/admin/blogs/{$blog->id}/edit");

    $blog->refresh();
    expect($blog->title)->toBe('Revised Title')
        ->and($blog->slug)->toBe('revised-title')
        ->and($blog->excerpt)->toBe('Revised excerpt.')
        ->and($blog->content)->toContain('<p>Revised</p>')
        ->and($blog->meta_description)->toBe('Revised meta.')
        ->and($blog->featured_image)->toBe('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png')
        ->and($blog->status)->toBe(PublishStatus::Draft);
});

it('only changes the fields that are sent', function () {
    $blog = Blog::factory()->draft()->create([
        'title' => 'Original Title',
        'content' => 'Original content.',
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['title' => 'New Title'])
        ->assertOk();

    $blog->refresh();
    expect($blog->title)->toBe('New Title')
        ->and($blog->content)->toBe('Original content.');
});

it('accepts a featured image url returned by the media endpoint', function () {
    $blog = Blog::factory()->draft()->create();
    Storage::disk('r2')->put('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.webp', 'image');

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", [
            'featured_image' => 'https://assets.nickbell.dev/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.webp',
        ])
        ->assertOk();

    expect($blog->refresh()->featured_image)->toBe('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.webp');
});

it('clears the featured image when null is sent', function () {
    $blog = Blog::factory()->draft()->create(['featured_image' => 'blog-images/old.png']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['featured_image' => null])
        ->assertOk()
        ->assertJsonPath('data.featured_image', null);

    expect($blog->refresh()->featured_image)->toBeNull();
});

it('rejects a featured image that was not uploaded through the media endpoint', function (string $featuredImage) {
    $blog = Blog::factory()->draft()->create();
    Storage::disk('r2')->put('elsewhere/01K6A1B2C3D4E5F6G7H8J9K0MN.png', 'image');

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['featured_image' => $featuredImage])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['featured_image']);

    expect($blog->refresh()->featured_image)->toBeNull();
})->with([
    'missing file' => ['blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png'],
    'outside the directory' => ['elsewhere/01K6A1B2C3D4E5F6G7H8J9K0MN.png'],
    'path traversal' => ['blog-images/../elsewhere/01K6A1B2C3D4E5F6G7H8J9K0MN.png'],
    'foreign url' => ['https://example.com/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png'],
]);

it('refuses to update a published blog', function () {
    $blog = Blog::factory()->published()->create(['title' => 'Live Post']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['title' => 'Hijacked'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only draft blogs can be updated through the API.');

    expect($blog->refresh()->title)->toBe('Live Post');
});

it('cannot publish a draft', function () {
    $blog = Blog::factory()->draft()->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", [
            'title' => 'Still A Draft',
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.published_at', null);

    $blog->refresh();
    expect($blog->title)->toBe('Still A Draft')
        ->and($blog->status)->toBe(PublishStatus::Draft)
        ->and($blog->published_at)->toBeNull();
});

it('validates slug uniqueness against other blogs', function () {
    Blog::factory()->create(['slug' => 'taken-slug']);
    $blog = Blog::factory()->draft()->create(['slug' => 'my-draft']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['slug' => 'taken-slug'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);

    expect($blog->refresh()->slug)->toBe('my-draft');
});

it('allows a draft to keep its own slug', function () {
    $blog = Blog::factory()->draft()->create(['slug' => 'my-draft']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['slug' => 'my-draft', 'title' => 'Renamed'])
        ->assertOk();
});

it('validates field lengths and required fields', function (array $payload, string $field) {
    $blog = Blog::factory()->draft()->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'title too long' => [['title' => str_repeat('a', 256)], 'title'],
    'meta description too long' => [['meta_description' => str_repeat('a', 256)], 'meta_description'],
    'title blanked' => [['title' => ''], 'title'],
    'content blanked' => [['content' => ''], 'content'],
]);

it('returns 404 for a missing blog', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson('/api/v1/blogs/999999', ['title' => 'Nope'])
        ->assertNotFound();
});

it('rejects unauthenticated update requests', function () {
    $blog = Blog::factory()->draft()->create(['title' => 'Original']);

    $this->patchJson("/api/v1/blogs/{$blog->id}", ['title' => 'Changed'])
        ->assertUnauthorized();

    expect($blog->refresh()->title)->toBe('Original');
});

it('refuses a published blog before validating the payload', function () {
    $blog = Blog::factory()->published()->create(['title' => 'Live Post']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", ['title' => str_repeat('a', 256)])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only draft blogs can be updated through the API.');

    expect($blog->refresh()->title)->toBe('Live Post');
});

it('accepts an image url from a disk whose public url has a path prefix', function () {
    Storage::fake('r2', ['url' => 'https://assets.nickbell.dev/media']);
    Storage::disk('r2')->put('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png', 'image');
    $blog = Blog::factory()->draft()->create();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->patchJson("/api/v1/blogs/{$blog->id}", [
            'featured_image' => 'https://assets.nickbell.dev/media/blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png',
        ])
        ->assertOk();

    expect($blog->refresh()->featured_image)->toBe('blog-images/01K6A1B2C3D4E5F6G7H8J9K0MN.png');
});
