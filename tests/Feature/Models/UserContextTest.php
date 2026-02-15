<?php

use App\Enums\UserContextKey;
use App\Models\UserContext;
use Illuminate\Support\Facades\Cache;

it('caches the value on first call', function () {
    UserContext::factory()->create(['key' => UserContextKey::CvSummary, 'value' => 'test_value']);

    $result = UserContext::cached(UserContextKey::CvSummary);

    expect($result)->toBe('test_value');
    expect(Cache::has('user_context.cv_summary'))->toBeTrue();
});

it('returns cached value on subsequent calls', function () {
    UserContext::factory()->create(['key' => UserContextKey::CvSummary, 'value' => 'original']);

    UserContext::cached(UserContextKey::CvSummary);

    // Delete the DB record - cached value should still return
    UserContext::where('key', UserContextKey::CvSummary)->delete();

    expect(UserContext::cached(UserContextKey::CvSummary))->toBe('original');
});

it('returns null when key does not exist', function () {
    expect(UserContext::cached(UserContextKey::CvDetailed))->toBeNull();
});

it('busts cache when record is updated', function () {
    $context = UserContext::factory()->create(['key' => UserContextKey::CvSummary, 'value' => 'old_value']);

    UserContext::cached(UserContextKey::CvSummary);
    expect(Cache::has('user_context.cv_summary'))->toBeTrue();

    $context->update(['value' => 'new_value']);

    expect(Cache::has('user_context.cv_summary'))->toBeFalse();

    expect(UserContext::cached(UserContextKey::CvSummary))->toBe('new_value');
});

it('busts cache when record is deleted', function () {
    $context = UserContext::factory()->create(['key' => UserContextKey::CvDetailed, 'value' => 'some_value']);

    UserContext::cached(UserContextKey::CvDetailed);
    expect(Cache::has('user_context.cv_detailed'))->toBeTrue();

    $context->delete();

    expect(Cache::has('user_context.cv_detailed'))->toBeFalse();
});

it('enforces unique key constraint', function () {
    UserContext::factory()->create(['key' => UserContextKey::CvSummary]);

    expect(fn () => UserContext::factory()->create(['key' => UserContextKey::CvSummary]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
