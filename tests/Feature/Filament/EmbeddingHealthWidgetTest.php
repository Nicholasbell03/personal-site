<?php

use App\Filament\Widgets\EmbeddingHealthWidget;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the embedding health widget', function () {
    Livewire::actingAs($this->user)
        ->test(EmbeddingHealthWidget::class)
        ->assertSuccessful()
        ->assertSee('Embedding Health');
});

it('shows correct missing embedding counts', function () {
    Blog::factory()->count(2)->create(['embedding_generated_at' => null]);
    Blog::factory()->create(['embedding_generated_at' => now()]);

    Project::factory()->create(['embedding_generated_at' => null]);
    Project::factory()->count(2)->create(['embedding_generated_at' => now()]);

    Share::factory()->count(3)->create(['embedding_generated_at' => null]);

    Livewire::actingAs($this->user)
        ->test(EmbeddingHealthWidget::class)
        ->assertSee('2/3')   // Blogs: 2 missing out of 3
        ->assertSee('1/3')   // Projects: 1 missing out of 3
        ->assertSee('3/3');  // Shares: 3 missing out of 3
});

it('shows all green when no embeddings are missing', function () {
    Blog::factory()->create(['embedding_generated_at' => now()]);
    Project::factory()->create(['embedding_generated_at' => now()]);
    Share::factory()->create(['embedding_generated_at' => now()]);

    Livewire::actingAs($this->user)
        ->test(EmbeddingHealthWidget::class)
        ->assertSee('0/1')
        ->assertSee('All embeddings generated');
});
