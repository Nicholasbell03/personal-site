<?php

namespace App\Services;

use App\Exceptions\XCreditsDepletedException;
use App\Exceptions\XQuoteNotAllowedException;
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
     * XPost shares in quote-tweet mode use summary only (the quoted post
     * provides the context). Everything else appends the URL so X can unfurl
     * a preview card.
     */
    public function composeTweet(Share $share, bool $asQuoteTweet = false): string
    {
        if ($asQuoteTweet) {
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
     * For XPost shares we attempt a true quote tweet first. If X rejects it
     * with the "not mentioned / not in conversation" restriction, we fall
     * back to a plain tweet of summary + original tweet URL, which X unfurls
     * into an embedded card.
     *
     * @return array{id: string, text: string}
     *
     * @throws \RuntimeException
     */
    public function postTweet(Share $share): array
    {
        $logContext = ['share_id' => $share->id];

        if ($share->isXPost() && ! empty($share->embed_data['tweet_id'])) {
            try {
                return $this->sendPayload([
                    'text' => $this->composeTweet($share, asQuoteTweet: true),
                    'quote_tweet_id' => $share->embed_data['tweet_id'],
                ], $logContext);
            } catch (XQuoteNotAllowedException $e) {
                Log::info('XPostingService: quote tweet not allowed, falling back to plain tweet with URL', array_merge([
                    'tweet_id' => $share->embed_data['tweet_id'],
                ], $logContext));
            }
        }

        return $this->sendPayload(
            ['text' => $this->composeTweet($share)],
            $logContext,
        );
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
            if ($response->status() === 403 && str_contains((string) $response->json('detail'), 'Quoting this post is not allowed')) {
                throw new XQuoteNotAllowedException("X API quote restriction: {$response->body()}");
            }

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
