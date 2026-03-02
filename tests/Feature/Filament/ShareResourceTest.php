<?php

use App\Enums\SourceType;
use App\Filament\Resources\Shares\Pages\CreateShare;
use App\Filament\Resources\Shares\Pages\EditShare;
use App\Filament\Resources\Shares\Pages\ListShares;
use App\Filament\Resources\Shares\ShareResource;
use App\Jobs\GenerateEmbeddingJob;
use App\Models\Share;
use App\Models\User;
use App\Services\OpenGraphService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render list page', function () {
    $this->actingAs($this->user)
        ->get(ShareResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list shares', function () {
    $shares = Share::factory()->count(3)->create();

    Livewire::actingAs($this->user)
        ->test(ListShares::class)
        ->assertCanSeeTableRecords($shares);
});

it('can render create page', function () {
    $this->actingAs($this->user)
        ->get(ShareResource::getUrl('create'))
        ->assertSuccessful();
});

it('can create share', function () {
    Livewire::actingAs($this->user)
        ->test(CreateShare::class)
        ->set('data.url', 'https://example.com/article')
        ->set('data.source_type', SourceType::Webpage->value)
        ->set('data.title', 'Test Share')
        ->set('data.slug', 'test-share')
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Share::class, [
        'url' => 'https://example.com/article',
        'title' => 'Test Share',
        'slug' => 'test-share',
    ]);
});

it('can validate required fields', function () {
    Livewire::actingAs($this->user)
        ->test(CreateShare::class)
        ->fillForm([
            'url' => '',
            'slug' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['url', 'slug']);
});

it('can render edit page', function () {
    $share = Share::factory()->create();

    $this->actingAs($this->user)
        ->get(ShareResource::getUrl('edit', ['record' => $share]))
        ->assertSuccessful();
});

it('can update share', function () {
    $share = Share::factory()->create(['slug' => 'original-slug']);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->set('data.url', 'https://example.com/updated')
        ->set('data.title', 'Updated Title')
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'title' => 'Updated Title',
    ]);
});

it('can delete share', function () {
    $share = Share::factory()->create();

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->callAction('delete');

    $this->assertDatabaseMissing(Share::class, [
        'id' => $share->id,
    ]);
});

it('can filter shares by source type', function () {
    $webpageShare = Share::factory()->create(['source_type' => SourceType::Webpage]);
    $youtubeShare = Share::factory()->youtube()->create();

    Livewire::actingAs($this->user)
        ->test(ListShares::class)
        ->assertCanSeeTableRecords([$webpageShare, $youtubeShare])
        ->filterTable('source_type', SourceType::Youtube->value)
        ->assertCanSeeTableRecords([$youtubeShare])
        ->assertCanNotSeeTableRecords([$webpageShare]);
});

it('can search shares by title', function () {
    $matchingShare = Share::factory()->create(['title' => 'Laravel Tips']);
    $nonMatchingShare = Share::factory()->create(['title' => 'Something Else']);

    Livewire::actingAs($this->user)
        ->test(ListShares::class)
        ->searchTable('Laravel')
        ->assertCanSeeTableRecords([$matchingShare])
        ->assertCanNotSeeTableRecords([$nonMatchingShare]);
});

it('can fetch metadata via header action on edit page and persist to database', function () {
    $share = Share::factory()->create([
        'url' => 'https://example.com/article',
        'title' => 'Old Title',
        'description' => 'Old description',
    ]);

    $this->mock(OpenGraphService::class)
        ->shouldReceive('refreshMetadata')
        ->once()
        ->withArgs(fn (Share $s) => $s->id === $share->id)
        ->andReturnUsing(function (Share $s) {
            $s->update([
                'title' => 'Fetched Title',
                'description' => 'Fetched description',
                'image_url' => 'https://example.com/image.jpg',
                'site_name' => 'Example',
                'source_type' => SourceType::Webpage,
            ]);

            return $s->refresh();
        });

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->callAction('fetchMetadata')
        ->assertSet('data.title', 'Fetched Title')
        ->assertSet('data.description', 'Fetched description')
        ->assertSet('data.image_url', 'https://example.com/image.jpg')
        ->assertSet('data.site_name', 'Example');

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'title' => 'Fetched Title',
        'description' => 'Fetched description',
    ]);
});

it('can fetch metadata via table row action', function () {
    $share = Share::factory()->create([
        'url' => 'https://example.com/article',
        'title' => 'Old Title',
    ]);

    $this->mock(OpenGraphService::class)
        ->shouldReceive('refreshMetadata')
        ->once()
        ->withArgs(fn (Share $s) => $s->id === $share->id)
        ->andReturnUsing(function (Share $s) {
            $s->update([
                'title' => 'Refreshed Title',
                'source_type' => SourceType::Webpage,
            ]);

            return $s->refresh();
        });

    Livewire::actingAs($this->user)
        ->test(ListShares::class)
        ->callTableAction('fetchMetadata', $share);

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'title' => 'Refreshed Title',
    ]);
});

it('does not overwrite slug when fetching metadata on edit page', function () {
    $share = Share::factory()->create([
        'url' => 'https://example.com/article',
        'slug' => 'original-slug',
    ]);

    $this->mock(OpenGraphService::class)
        ->shouldReceive('refreshMetadata')
        ->once()
        ->andReturnUsing(function (Share $s) {
            $s->update(['title' => 'New Title', 'source_type' => SourceType::Webpage]);

            return $s->refresh();
        });

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->callAction('fetchMetadata')
        ->assertSet('data.slug', 'original-slug');
});

it('preserves existing fields when metadata returns nulls', function () {
    $share = Share::factory()->create([
        'url' => 'https://example.com/no-og',
        'title' => 'Existing Title',
        'description' => 'Existing description',
    ]);

    $this->mock(OpenGraphService::class)
        ->shouldReceive('refreshMetadata')
        ->once()
        ->andReturnUsing(function (Share $s) {
            $s->update(['source_type' => SourceType::Webpage]);

            return $s->refresh();
        });

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->callAction('fetchMetadata')
        ->assertSet('data.title', 'Existing Title')
        ->assertSet('data.description', 'Existing description');

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'title' => 'Existing Title',
        'description' => 'Existing description',
    ]);
});

it('can edit share summary', function () {
    $share = Share::factory()->create(['summary' => 'Original summary']);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->set('data.summary', 'Updated summary')
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'summary' => 'Updated summary',
    ]);
});

it('can toggle post_to_x on edit', function () {
    $share = Share::factory()->create(['post_to_x' => true]);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->set('data.post_to_x', false)
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Share::class, [
        'id' => $share->id,
        'post_to_x' => false,
    ]);
});

it('shows regenerate embedding action on edit page when embedding is missing', function () {
    Queue::fake();

    $share = Share::factory()->create(['embedding_generated_at' => null]);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->assertActionVisible('regenerateEmbedding');
});

it('hides regenerate embedding action on edit page when embedding exists', function () {
    Queue::fake();

    $share = Share::factory()->create(['embedding_generated_at' => now()]);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->assertActionHidden('regenerateEmbedding');
});

it('dispatches embedding job via regenerate embedding action on edit page', function () {
    Queue::fake();

    $share = Share::factory()->create(['embedding_generated_at' => null]);

    Livewire::actingAs($this->user)
        ->test(EditShare::class, ['record' => $share->id])
        ->callAction('regenerateEmbedding')
        ->assertNotified('Embedding job dispatched');

    Queue::assertPushed(GenerateEmbeddingJob::class, fn ($job) => $job->model->is($share));
});

it('dispatches embedding job via regenerate embedding table action', function () {
    Queue::fake();

    $share = Share::factory()->create(['embedding_generated_at' => null]);

    Livewire::actingAs($this->user)
        ->test(ListShares::class)
        ->callTableAction('regenerateEmbedding', $share);

    Queue::assertPushed(GenerateEmbeddingJob::class, fn ($job) => $job->model->is($share));
});
