<?php

use App\Models\Share;

it('returns max three shares from featured endpoint', function () {
    Share::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/shares/featured');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('returns correct fields from featured endpoint', function () {
    Share::factory()->create();

    $response = $this->getJson('/api/v1/shares/featured');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'url',
                    'source_type',
                    'title',
                    'slug',
                    'description',
                    'image_url',
                    'site_name',
                    'created_at',
                ],
            ],
        ])
        ->assertJsonMissing(['commentary']);
});

it('orders featured endpoint by latest created', function () {
    $oldest = Share::factory()->create(['created_at' => now()->subDays(3)]);
    $newest = Share::factory()->create(['created_at' => now()]);
    $middle = Share::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->getJson('/api/v1/shares/featured');

    $response->assertOk();
    $data = $response->json('data');

    expect($data[0]['id'])->toBe($newest->id)
        ->and($data[1]['id'])->toBe($middle->id)
        ->and($data[2]['id'])->toBe($oldest->id);
});

it('returns paginated results from index endpoint', function () {
    Share::factory()->count(15)->create();

    $response = $this->getJson('/api/v1/shares');

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

it('returns full share by slug from show endpoint', function () {
    Share::factory()->create([
        'slug' => 'my-test-share',
        'commentary' => '<p>Great article</p>',
    ]);

    $response = $this->getJson('/api/v1/shares/my-test-share');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'url',
                'source_type',
                'title',
                'slug',
                'description',
                'image_url',
                'site_name',
                'commentary',
                'embed_data',
                'created_at',
            ],
        ])
        ->assertJson([
            'data' => [
                'slug' => 'my-test-share',
                'commentary' => '<p>Great article</p>',
            ],
        ]);
});

it('returns 404 for non-existent slug', function () {
    $response = $this->getJson('/api/v1/shares/non-existent-slug');

    $response->assertNotFound();
});
