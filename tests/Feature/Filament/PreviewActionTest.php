<?php

use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Http\Middleware\ValidatePreviewToken;
use App\Models\Blog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['app.preview_token' => 'test-preview-token', 'app.frontend_url' => 'https://nickbell.dev']);
});

it('links the preview button to a token scoped to this draft', function () {
    $this->freezeTime();
    $blog = Blog::factory()->draft()->create(['slug' => 'my-draft']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditBlog::class, ['record' => $blog->getRouteKey()])
        ->assertActionHasUrl('preview', 'https://nickbell.dev/blog/my-draft?token='.ValidatePreviewToken::issue('blogs', 'my-draft'));
});

it('hides the preview button when PREVIEW_TOKEN is not set', function () {
    config(['app.preview_token' => null]);
    $blog = Blog::factory()->draft()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditBlog::class, ['record' => $blog->getRouteKey()])
        ->assertActionHidden('preview');
});
