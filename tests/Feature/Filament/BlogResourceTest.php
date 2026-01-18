<?php

namespace Tests\Feature\Filament;

use App\Enums\BlogStatus;
use App\Filament\Resources\Blogs\BlogResource;
use App\Filament\Resources\Blogs\Pages\CreateBlog;
use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Filament\Resources\Blogs\Pages\ListBlogs;
use App\Models\Blog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_can_render_list_page(): void
    {
        $this->actingAs($this->user)
            ->get(BlogResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_list_blogs(): void
    {
        $blogs = Blog::factory()->count(3)->create();

        Livewire::actingAs($this->user)
            ->test(ListBlogs::class)
            ->assertCanSeeTableRecords($blogs);
    }

    public function test_can_render_create_page(): void
    {
        $this->actingAs($this->user)
            ->get(BlogResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_can_create_blog(): void
    {
        $newData = [
            'title' => 'My First Blog Post',
            'slug' => 'my-first-blog-post',
            'excerpt' => 'This is a short excerpt.',
            'content' => '<p>This is the full content of the blog post.</p>',
            'status' => BlogStatus::Draft->value,
            'meta_description' => 'A meta description for SEO.',
        ];

        Livewire::actingAs($this->user)
            ->test(CreateBlog::class)
            ->fillForm($newData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Blog::class, [
            'title' => 'My First Blog Post',
            'slug' => 'my-first-blog-post',
        ]);
    }

    public function test_can_validate_required_fields(): void
    {
        Livewire::actingAs($this->user)
            ->test(CreateBlog::class)
            ->fillForm([
                'title' => '',
                'slug' => '',
                'content' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title', 'slug', 'content']);
    }

    public function test_can_validate_unique_slug(): void
    {
        Blog::factory()->create(['slug' => 'existing-slug']);

        Livewire::actingAs($this->user)
            ->test(CreateBlog::class)
            ->fillForm([
                'title' => 'New Blog',
                'slug' => 'existing-slug',
                'content' => 'Some content',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_can_render_edit_page(): void
    {
        $blog = Blog::factory()->create();

        $this->actingAs($this->user)
            ->get(BlogResource::getUrl('edit', ['record' => $blog]))
            ->assertSuccessful();
    }

    public function test_can_update_blog(): void
    {
        $blog = Blog::factory()->create();

        $updatedData = [
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'content' => '<p>Updated content</p>',
            'status' => BlogStatus::Published->value,
        ];

        Livewire::actingAs($this->user)
            ->test(EditBlog::class, ['record' => $blog->id])
            ->fillForm($updatedData)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Blog::class, [
            'id' => $blog->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
        ]);
    }

    public function test_can_delete_blog(): void
    {
        $blog = Blog::factory()->create();

        Livewire::actingAs($this->user)
            ->test(EditBlog::class, ['record' => $blog->id])
            ->callAction('delete');

        $this->assertDatabaseMissing(Blog::class, [
            'id' => $blog->id,
        ]);
    }

    public function test_can_filter_blogs_by_status(): void
    {
        $draftBlog = Blog::factory()->draft()->create();
        $publishedBlog = Blog::factory()->published()->create();

        Livewire::actingAs($this->user)
            ->test(ListBlogs::class)
            ->assertCanSeeTableRecords([$draftBlog, $publishedBlog])
            ->filterTable('status', BlogStatus::Published->value)
            ->assertCanSeeTableRecords([$publishedBlog])
            ->assertCanNotSeeTableRecords([$draftBlog]);
    }

    public function test_can_search_blogs_by_title(): void
    {
        $matchingBlog = Blog::factory()->create(['title' => 'Laravel Tips']);
        $nonMatchingBlog = Blog::factory()->create(['title' => 'Something Else']);

        Livewire::actingAs($this->user)
            ->test(ListBlogs::class)
            ->searchTable('Laravel')
            ->assertCanSeeTableRecords([$matchingBlog])
            ->assertCanNotSeeTableRecords([$nonMatchingBlog]);
    }
}
