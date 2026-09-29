<?php

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Services\RelatedContentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Related content against real PostgreSQL + pgvector (run with phpunit.pgvector.xml).
 *
 * Embeddings are built by hand, so each item's cosine similarity to the viewed item is exact. The queue
 * is faked because saving content queues embedding and social-posting jobs, which would call AI providers.
 */
beforeEach(function () {
    expect(DB::getDriverName())->toBe('pgsql', 'Run with: vendor/bin/pest -c phpunit.pgvector.xml');

    Queue::fake();
});

/**
 * A unit vector whose cosine similarity to [1, 0, 0, ...] is exactly $similarity.
 *
 * @return list<float>
 */
function embeddingWithSimilarity(float $similarity): array
{
    $vector = array_fill(0, (int) config('services.embeddings.dimensions'), 0.0);
    $vector[0] = $similarity;
    $vector[1] = sqrt(1 - $similarity ** 2);

    return $vector;
}

/**
 * @template T of Model
 *
 * @param  T  $model
 * @return T
 */
function withSimilarity(Model $model, float $similarity): Model
{
    $model->forceFill(['embedding' => embeddingWithSimilarity($similarity)])->saveQuietly();

    return $model;
}

/**
 * @return list<string>
 */
function relatedTitles(Model $item): array
{
    return app(RelatedContentService::class)
        ->getRelatedItems($item)
        ->map(fn (array $related) => $related['item']->title)
        ->all();
}

/**
 * Three weak blogs plus a strong project and share. Ranked by similarity the top 3 are
 * Strong project, Strong share, Weak blog 1; by type order they would be the three blogs.
 */
function seedThreeWeakBlogsAndTwoStrongItems(): void
{
    withSimilarity(Blog::factory()->published()->create(['title' => 'Weak blog 1']), 0.35);
    withSimilarity(Blog::factory()->published()->create(['title' => 'Weak blog 2']), 0.33);
    withSimilarity(Blog::factory()->published()->create(['title' => 'Weak blog 3']), 0.31);
    withSimilarity(Project::factory()->published()->create(['title' => 'Strong project']), 0.92);
    withSimilarity(Share::factory()->create(['title' => 'Strong share']), 0.88);
}

it('ranks related items by similarity across content types', function () {
    $viewed = withSimilarity(Share::factory()->create(['title' => 'Viewed share']), 1.0);

    seedThreeWeakBlogsAndTwoStrongItems();

    expect(relatedTitles($viewed))->toBe(['Strong project', 'Strong share', 'Weak blog 1']);
});

it('leaves out items below the 0.3 similarity threshold', function () {
    $viewed = withSimilarity(Blog::factory()->published()->create(), 1.0);

    withSimilarity(Blog::factory()->published()->create(['title' => 'Just above']), 0.31);
    withSimilarity(Blog::factory()->published()->create(['title' => 'Just below']), 0.29);

    expect(relatedTitles($viewed))->toBe(['Just above']);
});

it('never includes the viewed item, drafts, or items without an embedding', function () {
    $viewed = withSimilarity(Blog::factory()->published()->create(), 1.0);

    withSimilarity(Blog::factory()->draft()->create(['title' => 'Draft blog']), 0.99);
    withSimilarity(Project::factory()->create(['title' => 'Draft project']), 0.99);
    Blog::factory()->published()->create(['title' => 'No embedding']);
    withSimilarity(Blog::factory()->published()->create(['title' => 'Published blog']), 0.5);

    expect(relatedTitles($viewed))->toBe(['Published blog']);
});

it('returns nothing when the viewed item has no embedding', function () {
    $viewed = Blog::factory()->published()->create();
    withSimilarity(Blog::factory()->published()->create(), 0.9);

    expect(relatedTitles($viewed))->toBe([]);
});

it('serves the ranked items from the related endpoint', function () {
    $viewed = withSimilarity(Blog::factory()->published()->create(), 1.0);

    seedThreeWeakBlogsAndTwoStrongItems();

    $this->getJson("/api/v1/blogs/{$viewed->slug}/related")
        ->assertOk()
        ->assertJsonPath('data.related.*.title', ['Strong project', 'Strong share', 'Weak blog 1'])
        ->assertJsonPath('data.related.*.type', ['project', 'share', 'blog']);
});
