<?php

namespace App\Services;

use App\Contracts\DownstreamPostable;
use App\Exceptions\LinkedInTokenExpiredException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPostingService
{
    private const POSTS_ENDPOINT = 'https://api.linkedin.com/rest/posts';

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
        $accessToken = config('services.linkedin.access_token');
        $personId = config('services.linkedin.person_id');

        if (! $accessToken || ! $personId) {
            throw new \RuntimeException('LinkedInPostingService: missing LinkedIn credentials');
        }

        $description = $postable->getDownstreamDescription();
        $imageUrl = $postable->getDownstreamImageUrl();

        $payload = [
            'author' => "urn:li:person:{$personId}",
            'commentary' => $description,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
            ],
            'content' => [
                'article' => [
                    'source' => $postable->getDownstreamUrl(),
                    'title' => $postable->getDownstreamTitle(),
                    'description' => $description,
                ],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];
        if ($imageUrl) {
            $payload['content']['article']['thumbnail'] = $imageUrl;
        }

        $response = Http::asJson()
            ->withToken($accessToken)
            ->withHeaders([
                'LinkedIn-Version' => '202401',
            ])
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

            throw new LinkedInTokenExpiredException("LinkedIn permission denied: {$response->body()}");
        }

        if (! $response->successful()) {
            Log::error('LinkedInPostingService: post failed', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new \RuntimeException("LinkedIn API returned {$response->status()}: {$response->body()}");
        }

        $postUrn = $response->header('x-restli-id') ?? $response->json('id') ?? '';

        if (trim($postUrn) === '') {
            Log::error('LinkedInPostingService: no post URN returned', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new \RuntimeException('LinkedInPostingService: no post URN returned from LinkedIn API');
        }

        Log::info('LinkedInPostingService: post published', array_merge([
            'post_urn' => $postUrn,
        ], $logContext));

        return $postUrn;
    }
}
