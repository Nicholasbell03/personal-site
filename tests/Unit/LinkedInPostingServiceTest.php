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
        'services.linkedin.api_version' => '202601',
    ]);
});

function mockPostable(?string $imageUrl = null): DownstreamPostable
{
    $postable = Mockery::mock(DownstreamPostable::class);
    $postable->shouldReceive('getDownstreamUrl')->andReturn('https://nickbell.dev/blog/test');
    $postable->shouldReceive('getDownstreamTitle')->andReturn('Test Blog');
    $postable->shouldReceive('getDownstreamDescription')->andReturn('A test description.');
    $postable->shouldReceive('getDownstreamImageUrl')->andReturn($imageUrl);

    return $postable;
}

it('posts successfully and returns post urn', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:123456',
        ]),
    ]);

    $service = new LinkedInPostingService;
    $result = $service->post(mockPostable());

    expect($result)->toBe('urn:li:share:123456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.linkedin.com/rest/posts'
            && $request->header('LinkedIn-Version')[0] === '202601'
            && $request['author'] === 'urn:li:person:test-person-id'
            && $request['content']['article']['source'] === 'https://nickbell.dev/blog/test'
            && ! isset($request['content']['article']['thumbnail']);
    });
});

it('uploads thumbnail and includes urn in post payload', function () {
    Http::fake([
        'https://api.nickbell.dev/storage/blog-images/test.jpg' => Http::response('fake-image-bytes', 200),
        'https://api.linkedin.com/rest/images*' => Http::response([
            'value' => [
                'uploadUrl' => 'https://www.linkedin.com/dms-uploads/test-upload',
                'image' => 'urn:li:image:C5qAQI123',
            ],
        ], 200),
        'https://www.linkedin.com/dms-uploads/test-upload' => Http::response(null, 201),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:789',
        ]),
    ]);

    $service = new LinkedInPostingService;
    $result = $service->post(mockPostable('https://api.nickbell.dev/storage/blog-images/test.jpg'));

    expect($result)->toBe('urn:li:share:789');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.linkedin.com/rest/posts'
            && ($request['content']['article']['thumbnail'] ?? null) === 'urn:li:image:C5qAQI123';
    });
});

it('posts without thumbnail when image upload fails', function () {
    Http::fake([
        'https://api.nickbell.dev/storage/blog-images/test.jpg' => Http::response('fake-image-bytes', 200),
        'https://api.linkedin.com/rest/images*' => Http::response(['message' => 'error'], 500),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:456',
        ]),
    ]);

    $service = new LinkedInPostingService;
    $result = $service->post(mockPostable('https://api.nickbell.dev/storage/blog-images/test.jpg'));

    expect($result)->toBe('urn:li:share:456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.linkedin.com/rest/posts'
            && ! isset($request['content']['article']['thumbnail']);
    });
});

it('posts without thumbnail when image download fails', function () {
    Http::fake([
        'https://api.nickbell.dev/storage/blog-images/missing.jpg' => Http::response(null, 404),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, [
            'x-restli-id' => 'urn:li:share:456',
        ]),
    ]);

    $service = new LinkedInPostingService;
    $result = $service->post(mockPostable('https://api.nickbell.dev/storage/blog-images/missing.jpg'));

    expect($result)->toBe('urn:li:share:456');
});

it('throws LinkedInTokenExpiredException on 401 response', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post(mockPostable()))
        ->toThrow(LinkedInTokenExpiredException::class);
});

it('throws LinkedInPermissionDeniedException on 403 response', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post(mockPostable()))
        ->toThrow(LinkedInPermissionDeniedException::class);
});

it('throws RuntimeException on other error responses', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Server error'], 500),
    ]);

    $service = new LinkedInPostingService;

    expect(fn () => $service->post(mockPostable()))
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
