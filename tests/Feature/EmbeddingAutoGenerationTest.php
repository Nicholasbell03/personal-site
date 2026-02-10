<?php

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Blog;
use App\Models\Share;
use Illuminate\Support\Facades\Queue;

it('dispatches embedding job when content field changes', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create();

    Queue::assertPushed(GenerateEmbeddingJob::class, 1);

    $blog->update(['title' => 'Updated Title']);

    Queue::assertPushed(GenerateEmbeddingJob::class, 2);
});

it('does not dispatch embedding job when non-content field changes', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create();

    // First dispatch from create
    Queue::assertPushed(GenerateEmbeddingJob::class, 1);

    $blog->update(['featured_image' => 'new-image.jpg']);

    // Should still be 1 - no new dispatch
    Queue::assertPushed(GenerateEmbeddingJob::class, 1);
});

it('dispatches embedding job when share commentary changes', function () {
    Queue::fake();

    $share = Share::factory()->create();

    Queue::assertPushed(GenerateEmbeddingJob::class, 1);

    $share->update(['commentary' => 'Updated thoughts on this']);

    Queue::assertPushed(GenerateEmbeddingJob::class, 2);
});

it('does not dispatch embedding job when share image_url changes', function () {
    Queue::fake();

    $share = Share::factory()->create();

    Queue::assertPushed(GenerateEmbeddingJob::class, 1);

    $share->update(['image_url' => 'https://example.com/new-image.jpg']);

    Queue::assertPushed(GenerateEmbeddingJob::class, 1);
});
