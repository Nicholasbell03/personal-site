<?php

use App\Filament\Pages\ApiTokens;
use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render the api tokens page', function () {
    $this->actingAs($this->user)
        ->get('/admin/api-tokens')
        ->assertSuccessful();
});

it('can generate a new token', function () {
    $component = Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->mountAction('generate')
        ->set('mountedActions.0.data.name', 'test-token')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->user->refresh();

    expect($this->user->tokens)->toHaveCount(1);
    expect($this->user->tokens->first()->name)->toBe('test-token');
    expect($component->get('plainTextToken'))->not->toBeNull();
});

it('requires a token name', function () {
    Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->mountAction('generate')
        ->set('mountedActions.0.data.name', '')
        ->callMountedAction()
        ->assertHasActionErrors(['name' => 'required']);
});

it('lists existing tokens', function () {
    $this->user->createToken('token-one');
    $this->user->createToken('token-two');

    Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->assertCanSeeTableRecords(
            PersonalAccessToken::where('tokenable_id', $this->user->id)->get()
        );
});

it('can revoke a token', function () {
    $token = $this->user->createToken('disposable');
    $tokenModel = $token->accessToken;

    Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->callTableAction('delete', $tokenModel);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $tokenModel->id,
    ]);
});

it('only shows tokens for the authenticated user', function () {
    $otherUser = User::factory()->create();
    $otherUser->createToken('other-token');
    $this->user->createToken('my-token');

    $otherToken = PersonalAccessToken::where('tokenable_id', $otherUser->id)->first();
    $myToken = PersonalAccessToken::where('tokenable_id', $this->user->id)->first();

    Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->assertCanSeeTableRecords([$myToken])
        ->assertCanNotSeeTableRecords([$otherToken]);
});

it('can dismiss the token banner', function () {
    $component = Livewire::actingAs($this->user)
        ->test(ApiTokens::class)
        ->mountAction('generate')
        ->set('mountedActions.0.data.name', 'temp-token')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($component->get('plainTextToken'))->not->toBeNull();

    $component->call('dismissToken');

    expect($component->get('plainTextToken'))->toBeNull();
});
