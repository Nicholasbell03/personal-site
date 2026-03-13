<?php

namespace App\Services;

use App\Contracts\DownstreamPostable;
use App\Exceptions\LinkedInPermissionDeniedException;
use App\Exceptions\LinkedInTokenExpiredException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPostingService
{
    private const POSTS_ENDPOINT = 'https://api.linkedin.com/rest/posts';

    private const IMAGES_ENDPOINT = 'https://api.linkedin.com/rest/images?action=initializeUpload';

    private string $accessToken;

    private string $personId;

    /**
     * Post content to LinkedIn.
     *
     * @param  array<string, mixed>  $logContext
     * @return string The LinkedIn post URN
     *
     * @throws LinkedInTokenExpiredException
     * @throws \RuntimeException
     */
    public function post(DownstreamPostable $postable, array $logContext = []): string
    {
        $this->accessToken = config('services.linkedin.access_token') ?? '';
        $this->personId = config('services.linkedin.person_id') ?? '';

        if (! $this->accessToken || ! $this->personId) {
            throw new \RuntimeException('LinkedInPostingService: missing LinkedIn credentials');
        }

        $description = $postable->getDownstreamDescription();
        $thumbnailUrn = $this->uploadThumbnail($postable->getDownstreamImageUrl(), $logContext);

        $article = [
            'source' => $postable->getDownstreamUrl(),
            'title' => $postable->getDownstreamTitle(),
            'description' => $description,
        ];

        if ($thumbnailUrn !== null) {
            $article['thumbnail'] = $thumbnailUrn;
        }

        $payload = [
            'author' => "urn:li:person:{$this->personId}",
            'commentary' => $description,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
            ],
            'content' => [
                'article' => $article,
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $response = $this->linkedInRequest()
            ->post(self::POSTS_ENDPOINT, $payload);

        if ($response->status() === 401) {
            Log::error('LinkedInPostingService: LinkedIn access token expired — refresh at https://www.linkedin.com/developers/. Token expires every 60 days.', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new LinkedInTokenExpiredException("LinkedIn token expired: {$response->body()}");
        }

        if ($response->status() === 403) {
            Log::error('LinkedInPostingService: LinkedIn permission denied — check app scopes (w_member_social) at https://www.linkedin.com/developers/', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new LinkedInPermissionDeniedException("LinkedIn permission denied: {$response->body()}");
        }

        if (! $response->successful()) {
            Log::error('LinkedInPostingService: post failed', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new \RuntimeException("LinkedIn API returned {$response->status()}: {$response->body()}");
        }

        $postUrn = $response->header('x-restli-id')
            ?: $response->header('x-linkedin-id')
            ?: ($response->json('id') ?? '');

        if (trim($postUrn) === '') {
            Log::warning('LinkedInPostingService: post created (201) but no URN returned — storing response headers for debugging', array_merge([
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ], $logContext));

            return '';
        }

        Log::info('LinkedInPostingService: post published', array_merge([
            'post_urn' => $postUrn,
        ], $logContext));

        return $postUrn;
    }

    /**
     * Upload an image to LinkedIn's Images API and return the image URN.
     *
     * @param  array<string, mixed>  $logContext
     */
    private function uploadThumbnail(?string $imageUrl, array $logContext): ?string
    {
        if (blank($imageUrl)) {
            return null;
        }

        try {
            // Run image download and LinkedIn upload init in parallel
            $responses = Http::pool(fn ($pool) => [
                $pool->as('image')->timeout(15)->get($imageUrl),
                $pool->as('init')->asJson()
                    ->withToken($this->accessToken)
                    ->withHeaders(['LinkedIn-Version' => config('services.linkedin.api_version')])
                    ->post(self::IMAGES_ENDPOINT, [
                        'initializeUploadRequest' => [
                            'owner' => "urn:li:person:{$this->personId}",
                        ],
                    ]),
            ]);

            if (! $responses['image']->successful()) {
                Log::warning('LinkedInPostingService: failed to download thumbnail image, posting without it', array_merge([
                    'image_url' => $imageUrl,
                    'status' => $responses['image']->status(),
                ], $logContext));

                return null;
            }

            if (! $responses['init']->successful()) {
                Log::warning('LinkedInPostingService: failed to initialize image upload, posting without thumbnail', array_merge([
                    'status' => $responses['init']->status(),
                    'body' => $responses['init']->body(),
                ], $logContext));

                return null;
            }

            $uploadUrl = $responses['init']->json('value.uploadUrl');
            $imageUrn = $responses['init']->json('value.image');

            if (! $uploadUrl || ! $imageUrn) {
                Log::warning('LinkedInPostingService: image upload init response missing uploadUrl or image URN', array_merge([
                    'body' => $responses['init']->body(),
                ], $logContext));

                return null;
            }

            $uploadResponse = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($responses['image']->body())
                ->put($uploadUrl);

            if (! $uploadResponse->successful()) {
                Log::warning('LinkedInPostingService: image binary upload failed, posting without thumbnail', array_merge([
                    'status' => $uploadResponse->status(),
                    'body' => $uploadResponse->body(),
                ], $logContext));

                return null;
            }

            Log::info('LinkedInPostingService: thumbnail uploaded', array_merge([
                'image_urn' => $imageUrn,
            ], $logContext));

            return $imageUrn;
        } catch (\Throwable $e) {
            Log::warning('LinkedInPostingService: thumbnail upload threw exception, posting without thumbnail', array_merge([
                'exception' => $e->getMessage(),
            ], $logContext));

            return null;
        }
    }

    private function linkedInRequest(): PendingRequest
    {
        return Http::asJson()
            ->withToken($this->accessToken)
            ->withHeaders([
                'LinkedIn-Version' => config('services.linkedin.api_version'),
            ]);
    }
}
