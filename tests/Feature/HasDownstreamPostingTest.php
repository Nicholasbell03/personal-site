<?php

use App\Enums\PublishStatus;
use App\Jobs\PostContentToXJob;
use App\Jobs\PostToLinkedInJob;
use App\Models\Blog;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

it('dispatches both jobs when blog transitions from draft to published', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->create();

    $blog->update(['status' => PublishStatus::Published]);

    Queue::assertPushed(PostContentToXJob::class, fn ($job) => $job->model->is($blog));
    Queue::assertPushed(PostToLinkedInJob::class, fn ($job) => $job->model->is($blog));
});

it('dispatches both jobs when project transitions from draft to published', function () {
    Queue::fake();

    $project = Project::factory()->draft()->create();

    $project->update(['status' => PublishStatus::Published]);

    Queue::assertPushed(PostContentToXJob::class, fn ($job) => $job->model->is($project));
    Queue::assertPushed(PostToLinkedInJob::class, fn ($job) => $job->model->is($project));
});

it('does not dispatch jobs when status does not change', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create();

    $blog->update(['title' => 'Updated title']);

    Queue::assertNotPushed(PostContentToXJob::class);
    Queue::assertNotPushed(PostToLinkedInJob::class);
});

it('does not dispatch x job when already posted to x', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->postedToX()->create();

    $blog->update(['status' => PublishStatus::Published]);

    Queue::assertNotPushed(PostContentToXJob::class);
    Queue::assertPushed(PostToLinkedInJob::class);
});

it('does not dispatch linkedin job when already posted to linkedin', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->postedToLinkedIn()->create();

    $blog->update(['status' => PublishStatus::Published]);

    Queue::assertPushed(PostContentToXJob::class);
    Queue::assertNotPushed(PostToLinkedInJob::class);
});

it('does not dispatch x job when post_to_x is false', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->create(['post_to_x' => false]);

    $blog->update(['status' => PublishStatus::Published]);

    Queue::assertNotPushed(PostContentToXJob::class);
    Queue::assertPushed(PostToLinkedInJob::class);
});

it('does not dispatch linkedin job when post_to_linkedin is false', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->create(['post_to_linkedin' => false]);

    $blog->update(['status' => PublishStatus::Published]);

    Queue::assertPushed(PostContentToXJob::class);
    Queue::assertNotPushed(PostToLinkedInJob::class);
});

it('does not dispatch jobs when transitioning to draft', function () {
    Queue::fake();

    $blog = Blog::factory()->published()->create();

    $blog->update(['status' => PublishStatus::Draft]);

    Queue::assertNotPushed(PostContentToXJob::class);
    Queue::assertNotPushed(PostToLinkedInJob::class);
});

it('sets published_at and dispatches jobs in the same save', function () {
    Queue::fake();

    $blog = Blog::factory()->draft()->create();

    expect($blog->published_at)->toBeNull();

    $blog->update(['status' => PublishStatus::Published]);

    $blog->refresh();

    expect($blog->published_at)->not->toBeNull();
    Queue::assertPushed(PostContentToXJob::class);
    Queue::assertPushed(PostToLinkedInJob::class);
});
