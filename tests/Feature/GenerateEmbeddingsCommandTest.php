<?php

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Fake the queue before creating models so we can track all dispatches
    Queue::fake();
});

it('dispatches jobs for models without embeddings', function () {
    Blog::factory()->published()->count(2)->create();
    Project::factory()->published()->count(1)->create();
    Share::factory()->count(1)->create();

    // 4 from create events
    Queue::assertPushed(GenerateEmbeddingJob::class, 4);

    $this->artisan('embeddings:generate')
        ->assertSuccessful();

    // 4 from create + 4 from command = 8
    Queue::assertPushed(GenerateEmbeddingJob::class, 8);
});

it('skips models that already have embeddings', function () {
    Blog::factory()->published()->create([
        'embedding_generated_at' => now(),
    ]);
    Blog::factory()->published()->create([
        'embedding_generated_at' => null,
    ]);

    // 2 from create events
    Queue::assertPushed(GenerateEmbeddingJob::class, 2);

    $this->artisan('embeddings:generate --model=blog')
        ->assertSuccessful();

    // 2 from create + 1 from command (only the null one) = 3
    Queue::assertPushed(GenerateEmbeddingJob::class, 3);
});

it('regenerates all embeddings with --force flag', function () {
    Blog::factory()->published()->count(3)->create([
        'embedding_generated_at' => now(),
    ]);

    // 3 from create events
    Queue::assertPushed(GenerateEmbeddingJob::class, 3);

    $this->artisan('embeddings:generate --model=blog --force')
        ->assertSuccessful();

    // 3 from create + 3 from command = 6
    Queue::assertPushed(GenerateEmbeddingJob::class, 6);
});

it('filters by specific model type', function () {
    Blog::factory()->published()->count(2)->create();
    Share::factory()->count(3)->create();

    // 5 from create events
    Queue::assertPushed(GenerateEmbeddingJob::class, 5);

    $this->artisan('embeddings:generate --model=share')
        ->assertSuccessful();

    // 5 from create + 3 from command (only shares) = 8
    Queue::assertPushed(GenerateEmbeddingJob::class, 8);
});

it('fails with invalid model name', function () {
    $this->artisan('embeddings:generate --model=invalid')
        ->assertFailed();
});
