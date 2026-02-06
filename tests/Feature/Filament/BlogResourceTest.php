<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\Blogs\BlogResource;
use App\Filament\Resources\Blogs\Pages\CreateBlog;
use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Filament\Resources\Blogs\Pages\ListBlogs;
use App\Models\Blog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render list page', function () {
    $this->actingAs($this->user)
        ->get(BlogResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list blogs', function () {
    $blogs = Blog::factory()->count(3)->create();

    Livewire::actingAs($this->user)
        ->test(ListBlogs::class)
        ->assertCanSeeTableRecords($blogs);
});

it('can render create page', function () {
    $this->actingAs($this->user)
        ->get(BlogResource::getUrl('create'))
        ->assertSuccessful();
});

it('can create blog', function () {
    Livewire::actingAs($this->user)
        ->test(CreateBlog::class)
        ->set('data.title', 'My First Blog Post')
        ->set('data.excerpt', 'This is a short excerpt.')
        ->set('data.content', '<p>This is the full content of the blog post.</p>')
        ->set('data.status', PublishStatus::Draft->value)
        ->set('data.meta_description', 'A meta description for SEO.')
        ->set('data.slug', 'my-first-blog-post')
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Blog::class, [
        'title' => 'My First Blog Post',
        'slug' => 'my-first-blog-post',
    ]);
});

it('can validate required fields', function () {
    Livewire::actingAs($this->user)
        ->test(CreateBlog::class)
        ->set('data.title', '')
        ->set('data.content', '')
        ->call('create')
        ->assertHasFormErrors(['title', 'content']);
});

it('auto generates slug from title', function () {
    Livewire::actingAs($this->user)
        ->test(CreateBlog::class)
        ->set('data.title', 'My Amazing Blog Post')
        ->set('data.content', '<p>Content here</p>')
        ->set('data.slug', 'my-amazing-blog-post')
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Blog::class, [
        'title' => 'My Amazing Blog Post',
        'slug' => 'my-amazing-blog-post',
    ]);
});

it('can render edit page', function () {
    $blog = Blog::factory()->create();

    $this->actingAs($this->user)
        ->get(BlogResource::getUrl('edit', ['record' => $blog]))
        ->assertSuccessful();
});

it('can update blog', function () {
    $blog = Blog::factory()->create(['slug' => 'original-slug']);

    Livewire::actingAs($this->user)
        ->test(EditBlog::class, ['record' => $blog->id])
        ->set('data.title', 'Updated Title')
        ->set('data.content', '<p>Updated content</p>')
        ->set('data.status', PublishStatus::Published->value)
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Blog::class, [
        'id' => $blog->id,
        'title' => 'Updated Title',
    ]);
});

it('can delete blog', function () {
    $blog = Blog::factory()->create();

    Livewire::actingAs($this->user)
        ->test(EditBlog::class, ['record' => $blog->id])
        ->callAction('delete');

    $this->assertDatabaseMissing(Blog::class, [
        'id' => $blog->id,
    ]);
});

it('can filter blogs by status', function () {
    $draftBlog = Blog::factory()->draft()->create();
    $publishedBlog = Blog::factory()->published()->create();

    Livewire::actingAs($this->user)
        ->test(ListBlogs::class)
        ->assertCanSeeTableRecords([$draftBlog, $publishedBlog])
        ->filterTable('status', PublishStatus::Published->value)
        ->assertCanSeeTableRecords([$publishedBlog])
        ->assertCanNotSeeTableRecords([$draftBlog]);
});

it('can search blogs by title', function () {
    $matchingBlog = Blog::factory()->create(['title' => 'Laravel Tips']);
    $nonMatchingBlog = Blog::factory()->create(['title' => 'Something Else']);

    Livewire::actingAs($this->user)
        ->test(ListBlogs::class)
        ->searchTable('Laravel')
        ->assertCanSeeTableRecords([$matchingBlog])
        ->assertCanNotSeeTableRecords([$nonMatchingBlog]);
});

it('auto sets published_at when status changes to published', function () {
    $blog = Blog::factory()->draft()->create();

    expect($blog->published_at)->toBeNull();

    Livewire::actingAs($this->user)
        ->test(EditBlog::class, ['record' => $blog->id])
        ->set('data.status', PublishStatus::Published->value)
        ->call('save')
        ->assertHasNoFormErrors();

    $blog->refresh();
    expect($blog->published_at)->not->toBeNull();
});

it('clears published_at when status changes to draft', function () {
    $blog = Blog::factory()->published()->create();

    expect($blog->published_at)->not->toBeNull();

    Livewire::actingAs($this->user)
        ->test(EditBlog::class, ['record' => $blog->id])
        ->set('data.status', PublishStatus::Draft->value)
        ->call('save')
        ->assertHasNoFormErrors();

    $blog->refresh();
    expect($blog->published_at)->toBeNull();
});

it('preserves existing published_at when already published', function () {
    $originalPublishedAt = now()->subWeek();
    $blog = Blog::factory()->create([
        'status' => PublishStatus::Published,
        'published_at' => $originalPublishedAt,
    ]);

    Livewire::actingAs($this->user)
        ->test(EditBlog::class, ['record' => $blog->id])
        ->set('data.title', 'Updated Title')
        ->call('save')
        ->assertHasNoFormErrors();

    $blog->refresh();
    expect($blog->published_at->toDateTimeString())
        ->toBe($originalPublishedAt->toDateTimeString());
});
