<?php

use App\Models\Project;
use App\Models\Technology;

it('returns only featured technologies', function () {
    Technology::factory()->featured()->create(['name' => 'Featured Tech']);
    Technology::factory()->create(['name' => 'Unfeatured Tech']);

    $response = $this->getJson('/api/v1/technologies');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Featured Tech')
        ->not->toContain('Unfeatured Tech');
});

it('returns correct resource structure', function () {
    Technology::factory()->featured()->create();

    $response = $this->getJson('/api/v1/technologies');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'name',
                    'slug',
                    'project_count',
                ],
            ],
        ]);
});

it('counts only published projects', function () {
    $tech = Technology::factory()->featured()->create(['name' => 'CountTech']);
    $tech->projects()->attach(
        Project::factory()->published()->count(2)->create()
    );
    $tech->projects()->attach(
        Project::factory()->draft()->create()
    );

    $response = $this->getJson('/api/v1/technologies');

    $response->assertOk();

    expect($response->json('data.0.project_count'))->toBe(2);
});

it('orders alphabetically', function () {
    Technology::factory()->featured()->create(['name' => 'Zephyr']);
    Technology::factory()->featured()->create(['name' => 'Alpha']);

    $response = $this->getJson('/api/v1/technologies');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Alpha', 'Zephyr']);
});

it('returns empty data when none featured', function () {
    Technology::factory()->create(['name' => 'Unfeatured']);

    $response = $this->getJson('/api/v1/technologies');

    $response->assertOk();

    expect($response->json('data'))->toBeEmpty();
});
