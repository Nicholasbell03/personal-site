<?php

namespace App\Services;

use App\Enums\SourceType;
use App\Exceptions\XCreditsDepletedException;
use App\Models\Share;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XPostingService
{
    private const TWEETS_ENDPOINT = 'https://api.x.com/2/tweets';

    private const TCO_URL_LENGTH = 23;

    /**
     * Compose the tweet text for a share.
     *
     * X posts use quote tweet (summary only), everything else uses summary + URL.
     */
    public function composeTweet(Share $share): string
    {
        if ($share->source_type === SourceType::XPost) {
            return $share->summary ?? '';
        }

        $url = $share->url;
        $maxSummaryLength = 280 - self::TCO_URL_LENGTH - 2; // 2 for "\n\n"
        $summary = mb_substr($share->summary ?? '', 0, $maxSummaryLength);

        return "{$summary}\n\n{$url}";
    }

    /**
     * Post a tweet for the given share.
     *
     * @return array{id: string, text: string}
     *
     * @throws \RuntimeException
     */
    public function postTweet(Share $share): array
    {
        $text = $this->composeTweet($share);
        $payload = ['text' => $text];

        if ($share->source_type === SourceType::XPost && ! empty($share->embed_data['tweet_id'])) {
            $payload['quote_tweet_id'] = $share->embed_data['tweet_id'];
        }

        return $this->sendPayload($payload, ['share_id' => $share->id]);
    }

    /**
     * Post arbitrary text as a tweet.
     *
     * @param  array<string, mixed>  $logContext
     * @return array{id: string, text: string}
     *
     * @throws \RuntimeException
     */
    public function postText(string $text, array $logContext = []): array
    {
        return $this->sendPayload(['text' => $text], $logContext);
    }

    /**
     * Send a payload to the X API and handle the response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $logContext
     * @return array{id: string, text: string}
     *
     * @throws XCreditsDepletedException
     * @throws \RuntimeException
     */
    private function sendPayload(array $payload, array $logContext = []): array
    {
        $consumerKey = config('services.x.api_key');
        $consumerSecret = config('services.x.api_secret');
        $accessToken = config('services.x.access_token');
        $accessTokenSecret = config('services.x.access_token_secret');

        if (! $consumerKey || ! $consumerSecret || ! $accessToken || ! $accessTokenSecret) {
            throw new \RuntimeException('XPostingService: missing X/Twitter OAuth credentials');
        }

        /** @var Response $response */
        $response = Http::asJson()
            ->withToken($this->buildOAuthHeader($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret), 'OAuth')
            ->post(self::TWEETS_ENDPOINT, $payload);

        if ($response->status() === 402) {
            Log::warning('XPostingService: X API credits depleted', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new XCreditsDepletedException("X API credits depleted: {$response->body()}");
        }

        if (! $response->successful()) {
            Log::error('XPostingService: tweet posting failed', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new \RuntimeException("X API returned {$response->status()}: {$response->body()}");
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['id']) || ! is_string($data['id']) || trim($data['id']) === '') {
            Log::error('XPostingService: malformed success payload from X API', array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $logContext));

            throw new \RuntimeException('XPostingService: malformed X API response payload');
        }

        $normalizedData = [
            'id' => $data['id'],
            'text' => isset($data['text']) && is_string($data['text']) ? $data['text'] : '',
        ];

        Log::info('XPostingService: tweet posted', array_merge([
            'tweet_id' => $normalizedData['id'],
        ], $logContext));

        return $normalizedData;
    }

    /**
     * Build OAuth 1.0a Authorization header value.
     */
    private function buildOAuthHeader(string $consumerKey, string $consumerSecret, string $accessToken, string $accessTokenSecret): string
    {
        $oauthParams = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0',
        ];

        $sigParams = $oauthParams;
        ksort($sigParams);

        $paramString = http_build_query($sigParams, '', '&', PHP_QUERY_RFC3986);

        $signatureBase = 'POST&'
            .rawurlencode(self::TWEETS_ENDPOINT).'&'
            .rawurlencode($paramString);

        $signingKey = rawurlencode($consumerSecret).'&'.rawurlencode($accessTokenSecret);
        $signature = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));

        $oauthParams['oauth_signature'] = $signature;
        ksort($oauthParams);

        $parts = [];
        foreach ($oauthParams as $key => $value) {
            $parts[] = rawurlencode($key).'="'.rawurlencode($value).'"';
        }

        return implode(', ', $parts);
    }
}
