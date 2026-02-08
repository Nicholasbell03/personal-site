<?php

namespace App\Services;

use App\Enums\SourceType;
use App\Models\Share;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenGraphService
{
    /** @var list<string> */
    public const METADATA_FIELDS = [
        'title',
        'description',
        'image_url',
        'site_name',
        'source_type',
        'embed_data',
        'og_raw',
    ];

    /**
     * Fetch OG metadata from a URL.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string, source_type: SourceType, embed_data: ?array<string, string>, og_raw: ?array<string, string>}
     */
    public function fetch(string $url): array
    {
        $sourceType = $this->detectSourceType($url);
        $embedData = $this->extractEmbedData($url, $sourceType);

        if (! $this->isSafeUrl($url)) {
            return [
                'title' => null,
                'description' => null,
                'image' => null,
                'site_name' => null,
                'source_type' => $sourceType,
                'embed_data' => $embedData,
                'og_raw' => null,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->maxRedirects(3)
                ->withHeaders([
                    'User-Agent' => 'NickBellBot/1.0 (+https://nickbell.dev)',
                ])
                ->withOptions(['stream' => true])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('OpenGraph fetch failed: non-successful HTTP response', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [
                    'title' => null,
                    'description' => null,
                    'image' => null,
                    'site_name' => null,
                    'source_type' => $sourceType,
                    'embed_data' => $embedData,
                    'og_raw' => null,
                ];
            }

            $ogTags = $this->parseOgTags($response->body());

            if (empty($ogTags)) {
                Log::warning('OpenGraph fetch returned no OG tags', [
                    'url' => $url,
                    'response_size' => strlen($response->body()),
                ]);
            }

            return [
                'title' => $ogTags['og:title'] ?? null,
                'description' => $ogTags['og:description'] ?? null,
                'image' => $ogTags['og:image'] ?? null,
                'site_name' => $ogTags['og:site_name'] ?? null,
                'source_type' => $sourceType,
                'embed_data' => $embedData,
                'og_raw' => $ogTags ?: null,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenGraph fetch exception', [
                'url' => $url,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'title' => null,
                'description' => null,
                'image' => null,
                'site_name' => null,
                'source_type' => $sourceType,
                'embed_data' => $embedData,
                'og_raw' => null,
            ];
        }
    }

    /**
     * Fetch OG metadata and update the share model directly.
     */
    public function refreshMetadata(Share $share): Share
    {
        $data = $this->fetch($share->url);

        $ogContent = array_filter([
            'title' => $data['title'],
            'description' => $data['description'],
            'image_url' => $data['image'],
            'site_name' => $data['site_name'],
            'og_raw' => $data['og_raw'],
        ], fn ($value) => $value !== null && $value !== '');

        $attributes = array_filter([
            ...$ogContent,
            'source_type' => $data['source_type'],
            'embed_data' => $data['embed_data'],
        ], fn ($value) => $value !== null && $value !== '');

        if (empty($ogContent)) {
            Log::warning('OpenGraph refreshMetadata: fetch returned no OG content', [
                'share_id' => $share->id,
                'url' => $share->url,
            ]);
        }

        $share->update($attributes);

        Log::info('OpenGraph refreshMetadata: share updated', [
            'share_id' => $share->id,
            'url' => $share->url,
            'updated_fields' => array_keys($attributes),
        ]);

        return $share->refresh();
    }

    public function detectSourceType(string $url): SourceType
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return SourceType::Webpage;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host));

        if (in_array($host, ['youtube.com', 'youtu.be', 'm.youtube.com'])) {
            return SourceType::Youtube;
        }

        if (in_array($host, ['x.com', 'twitter.com', 'mobile.twitter.com'])) {
            return SourceType::XPost;
        }

        return SourceType::Webpage;
    }

    /**
     * @return array<string, string>|null
     */
    public function extractEmbedData(string $url, SourceType $type): ?array
    {
        return match ($type) {
            SourceType::Youtube => $this->extractYoutubeId($url),
            SourceType::XPost => $this->extractTweetId($url),
            default => null,
        };
    }

    /**
     * @return array{video_id: string}|null
     */
    private function extractYoutubeId(string $url): ?array
    {
        // youtu.be/VIDEO_ID
        if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $matches)) {
            return ['video_id' => $matches[1]];
        }

        // youtube.com/watch?v=VIDEO_ID
        if (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#', $url, $matches)) {
            return ['video_id' => $matches[1]];
        }

        // youtube.com/embed/VIDEO_ID
        if (preg_match('#/embed/([a-zA-Z0-9_-]{11})#', $url, $matches)) {
            return ['video_id' => $matches[1]];
        }

        // youtube.com/shorts/VIDEO_ID
        if (preg_match('#/shorts/([a-zA-Z0-9_-]{11})#', $url, $matches)) {
            return ['video_id' => $matches[1]];
        }

        return null;
    }

    /**
     * @return array{tweet_id: string}|null
     */
    private function extractTweetId(string $url): ?array
    {
        if (preg_match('#/status/(\d+)#', $url, $matches)) {
            return ['tweet_id' => $matches[1]];
        }

        return null;
    }

    /**
     * Validate that a URL is safe to fetch (prevents SSRF).
     */
    private function isSafeUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array(strtolower($scheme ?? ''), ['http', 'https'])) {
            Log::warning('OpenGraph SSRF check failed: invalid scheme', [
                'url' => $url,
                'scheme' => $scheme,
            ]);

            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            Log::warning('OpenGraph SSRF check failed: no host', [
                'url' => $url,
            ]);

            return false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || empty($records)) {
            Log::warning('OpenGraph SSRF check failed: DNS resolution returned no records', [
                'url' => $url,
                'host' => $host,
            ]);

            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! $ip) {
                continue;
            }

            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                Log::warning('OpenGraph SSRF check failed: resolved to private/reserved IP', [
                    'url' => $url,
                    'host' => $host,
                    'ip' => $ip,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function parseOgTags(string $html): array
    {
        $tags = [];

        $previousLibxmlState = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument;
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);
        }

        $xpath = new \DOMXPath($dom);
        $metas = $xpath->query('//meta[starts-with(@property, "og:")]');

        if ($metas) {
            foreach ($metas as $meta) {
                if (! $meta instanceof \DOMElement) {
                    continue;
                }
                $property = $meta->getAttribute('property');
                $content = $meta->getAttribute('content');
                if ($property && $content) {
                    $tags[$property] = $content;
                }
            }
        }

        return $tags;
    }
}
