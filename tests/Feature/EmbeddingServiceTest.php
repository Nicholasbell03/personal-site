<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Technology;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;

it('generates and stores embedding for a model', function () {
    Queue::fake();

    $fakeEmbedding = Embeddings::fakeEmbedding(1536);
    Embeddings::fake([
        new EmbeddingsResponse([$fakeEmbedding], 100, new Meta('openai', 'text-embedding-3-small')),
    ]);

    $blog = Blog::factory()->published()->create([
        'title' => 'Test Blog',
        'excerpt' => 'An excerpt',
        'content' => 'Some content here',
    ]);

    $service = new EmbeddingService;
    $result = $service->generateFor($blog);

    expect($result)->toBeTrue();

    $blog->refresh();
    expect($blog->embedding)->toBeArray()
        ->and($blog->embedding)->toHaveCount(1536)
        ->and($blog->embedding_generated_at)->not->toBeNull();
});

it('skips models with empty embeddable text', function () {
    Queue::fake();
    Embeddings::fake();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'empty embeddable text'));

    $blog = Blog::factory()->published()->create([
        'title' => '',
        'excerpt' => null,
        'content' => '',
    ]);

    $service = new EmbeddingService;
    $result = $service->generateFor($blog);

    expect($result)->toBeFalse();

    Embeddings::assertNothingGenerated();
});

it('logs error and returns false on API failure', function () {
    Queue::fake();

    Embeddings::fake(function () {
        throw new \RuntimeException('API connection failed');
    });

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'embedding generation failed'));

    $blog = Blog::factory()->published()->create([
        'title' => 'Test Blog',
        'content' => 'Some content',
    ]);

    $service = new EmbeddingService;
    $result = $service->generateFor($blog);

    expect($result)->toBeFalse();
});

it('uses saveQuietly to prevent re-triggering model events', function () {
    Queue::fake();

    $fakeEmbedding = Embeddings::fakeEmbedding(1536);
    Embeddings::fake([
        new EmbeddingsResponse([$fakeEmbedding], 100, new Meta('openai', 'text-embedding-3-small')),
    ]);

    $blog = Blog::factory()->published()->create([
        'title' => 'Test Blog',
        'content' => 'Content here',
    ]);

    $service = new EmbeddingService;
    $service->generateFor($blog);

    Embeddings::assertGenerated(function ($inputs) {
        return count($inputs) === 1;
    });
});

it('builds embeddable text for project with technologies from database', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'title' => 'Cool Project',
        'description' => 'A cool description',
        'long_description' => 'Detailed info here',
    ]);

    $react = Technology::factory()->create(['name' => 'React']);
    $laravel = Technology::factory()->create(['name' => 'Laravel']);
    $project->technologies()->attach([$react->id, $laravel->id]);

    $text = $project->getEmbeddableText();

    expect($text)
        ->toContain('Cool Project')
        ->toContain('A cool description')
        ->toContain('Detailed info here')
        ->toContain('Technologies:')
        ->toContain('React')
        ->toContain('Laravel');
});
