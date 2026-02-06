<?php

use App\Enums\SourceType;
use App\Filament\Resources\Shares\Pages\CreateShare;
use App\Filament\Resources\Shares\Pages\EditShare;
use App\Filament\Resources\Shares\Pages\ListShares;
use App\Filament\Resources\Shares\ShareResource;
use App\Models\Share;
use App\Models\User;
use Filament\Facades\Filament;
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
