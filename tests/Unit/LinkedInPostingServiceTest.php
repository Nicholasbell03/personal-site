<?php

use App\Contracts\DownstreamPostable;
use App\Exceptions\LinkedInPermissionDeniedException;
use App\Exceptions\LinkedInTokenExpiredException;
use App\Services\LinkedInPostingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.linkedin.access_token' => 'test-token',
        'services.linkedin.person_id' => 'test-person-id',
    ]);
});

it('posts successfully and returns post urn', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:123456',
        ]),
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn('https://cdn.example.com/image.jpg');

    $service = new LinkedInPostingService;
    $result = $service->post($postable);

    expect($result)->toBe('urn:li:share:123456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.linkedin.com/rest/posts'
            && $request->header('LinkedIn-Version')[0] === '202502'
            && $request['author'] === 'urn:li:person:test-person-id'
            && $request['content']['article']['source'] === 'https://nickbell.dev/blog/test'
            && $request['content']['article']['thumbnail'] === 'https://cdn.example.com/image.jpg';
    });
});

it('omits thumbnail when image url is null', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:789',
        ]),
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn(null);

    $service = new LinkedInPostingService;
    $result = $service->post($postable);

    expect($result)->toBe('urn:li:share:789');

    Http::assertSent(function ($request) {
        return ! isset($request['content']['article']['thumbnail']);
    });
});

it('throws LinkedInTokenExpiredException on 401 response', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn(null);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post($postable))
        ->toThrow(LinkedInTokenExpiredException::class);
});

it('throws LinkedInPermissionDeniedException on 403 response', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn(null);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post($postable))
        ->toThrow(LinkedInPermissionDeniedException::class);
});

it('throws RuntimeException on other error responses', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Server error'], 500),
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn(null);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post($postable))
        ->toThrow(\RuntimeException::class, 'LinkedIn API returned 500');
});

it('throws RuntimeException when credentials are missing', function () {
    config([
        'services.linkedin.access_token' => null,
        'services.linkedin.person_id' => null,
    ]);

    $postable = Mockery::mock(DownstreamPostable::class);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post($postable))
        ->toThrow(\RuntimeException::class, 'missing LinkedIn credentials');
});
