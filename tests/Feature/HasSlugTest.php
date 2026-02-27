<?php

use App\Models\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a slug from the title when creating a share', function () {
    $share = Share::factory()->create([
        'title' => 'My Great Article',
        'slug' => null,
    ]);

    expect($share->slug)->toBe('my-great-article');
});

it('appends -2 suffix when a duplicate slug exists', function () {
    Share::factory()->create([
        'title' => 'Duplicate Title',
        'slug' => 'duplicate-title',
    ]);

    $share = Share::factory()->create([
        'title' => 'Duplicate Title',
        'slug' => null,
    ]);

    expect($share->slug)->toBe('duplicate-title-2');
});

it('appends incrementing suffixes for multiple duplicate slugs', function () {
    Share::factory()->create([
        'title' => 'Same Title',
        'slug' => 'same-title',
    ]);

    $second = Share::factory()->create([
        'title' => 'Same Title',
        'slug' => null,
    ]);

    $third = Share::factory()->create([
        'title' => 'Same Title',
        'slug' => null,
    ]);

    expect($second->slug)->toBe('same-title-2');
    expect($third->slug)->toBe('same-title-3');
});

it('generates a unique slug from the fallback path when title is empty', function () {
    $share = Share::factory()->create([
        'url' => 'https://x.com/karpathy/status/123456',
        'title' => null,
        'slug' => null,
    ]);

    expect($share->slug)
        ->toStartWith('xcom-')
        ->not->toBeEmpty();
});
