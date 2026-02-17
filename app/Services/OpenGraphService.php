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
        'author',
        'source_type',
        'embed_data',
        'og_raw',
    ];

    // ── Public API ───────────────────────────────────────────────

    /**
     * Fetch OG metadata from a URL.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string, author: ?string, source_type: SourceType, embed_data: ?array<string, string>, og_raw: ?array<string, string>}
     */
    public function fetch(string $url): array
    {
        $sourceType = $this->detectSourceType($url);
        $embedData = $this->extractEmbedData($url, $sourceType);

        if ($sourceType === SourceType::Youtube && isset($embedData['video_id'])) {
            $youtubeData = $this->fetchYoutubeMetadata($embedData['video_id']);

            if ($youtubeData !== null) {
                return [
                    ...$youtubeData,
                    'source_type' => $sourceType,
                    'embed_data' => $embedData,
                    'og_raw' => null,
                ];
            }
        }

        return $this->fetchViaOgScraping($url, $sourceType, $embedData);
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
            'author' => $data['author'],
            'og_raw' => $data['og_raw'],
        ], fn ($value) => $value !== null && $value !== '');

        $attributes = array_filter([
            ...$ogContent,
            'source_type' => $data['source_type'],
            'embed_data' => $data['embed_data'],
        ], fn ($value) => $value !== null);

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

        if ($host === 'linkedin.com') {
            return SourceType::LinkedIn;
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

    // ── Fetch strategies (private) ──────────────────────────────

    /**
     * Fetch video metadata from the YouTube Data API v3.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: string, author: ?string}|null
     */
    private function fetchYoutubeMetadata(string $videoId): ?array
    {
        $apiKey = config('services.google.youtube_api_key');

        if (empty($apiKey)) {
            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet',
                    'id' => $videoId,
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                Log::warning('YouTube API request failed', [
                    'video_id' => $videoId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $items = $response->json('items', []);

            if (empty($items)) {
                Log::warning('YouTube API returned no items for video', [
                    'video_id' => $videoId,
                ]);

                return null;
            }

            $snippet = $items[0]['snippet'] ?? [];
            $thumbnails = $snippet['thumbnails'] ?? [];

            $thumbnail = $thumbnails['maxres']
                ?? $thumbnails['standard']
                ?? $thumbnails['high']
                ?? $thumbnails['medium']
                ?? $thumbnails['default']
                ?? null;

            Log::info('YouTube API metadata fetched successfully', [
                'video_id' => $videoId,
                'title' => $snippet['title'] ?? null,
            ]);

            return [
                'title' => $snippet['title'] ?? null,
                'description' => $snippet['description'] ?? null,
                'image' => $thumbnail['url'] ?? null,
                'site_name' => 'YouTube',
                'author' => $snippet['channelTitle'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('YouTube API exception', [
                'video_id' => $videoId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Fetch metadata by scraping OG tags from the URL's HTML.
     *
     * @param  array<string, string>|null  $embedData
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string, author: ?string, source_type: SourceType, embed_data: ?array<string, string>, og_raw: ?array<string, string>}
     */
    private function fetchViaOgScraping(string $url, SourceType $sourceType, ?array $embedData): array
    {
        if (! $this->isSafeUrl($url)) {
            return $this->emptyResult($sourceType, $embedData);
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)
                ->maxRedirects(3)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NickBellBot/1.0; +https://nickbell.dev)',
                    'Accept' => 'text/html',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('OpenGraph fetch failed: non-successful HTTP response', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return $this->emptyResult($sourceType, $embedData);
            }

            $ogTags = $this->parseOgTags($response->body());

            if (empty($ogTags)) {
                Log::warning('OpenGraph fetch returned no OG tags', [
                    'url' => $url,
                    'response_size' => strlen($response->body()),
                ]);
            }

            $author = $this->extractAuthor($url, $sourceType, $ogTags);

            return [
                'title' => $ogTags['og:title'] ?? null,
                'description' => $ogTags['og:description'] ?? null,
                'image' => $ogTags['og:image'] ?? null,
                'site_name' => $ogTags['og:site_name'] ?? null,
                'author' => $author,
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

            return $this->emptyResult($sourceType, $embedData);
        }
    }

    // ── Author extraction (private) ─────────────────────────────

    /**
     * Extract author from URL patterns or OG tags based on source type.
     *
     * @param  array<string, string>  $ogTags
     */
    private function extractAuthor(string $url, SourceType $sourceType, array $ogTags): ?string
    {
        if ($sourceType === SourceType::XPost) {
            $path = parse_url($url, PHP_URL_PATH);

            if ($path && preg_match('#^/([^/]+)/status/\d+#', $path, $matches)) {
                return '@'.$matches[1];
            }

            return null;
        }

        if ($sourceType === SourceType::LinkedIn) {
            $path = parse_url($url, PHP_URL_PATH);

            if ($path && preg_match('#/in/([^/]+)#', $path, $matches)) {
                return str_replace('-', ' ', $matches[1]);
            }

            return $ogTags['article:author'] ?? null;
        }

        return $ogTags['article:author'] ?? null;
    }

    // ── URL analysis (private) ──────────────────────────────────

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

    // ── Security (private) ──────────────────────────────────────

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

    // ── HTML parsing (private) ──────────────────────────────────

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

        // Check property attribute (standard OG + article:*)
        $metas = $xpath->query('//meta[starts-with(@property, "og:") or starts-with(@property, "article:")]');
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

        // Fallback: check name attribute (some sites use name instead of property)
        if (empty($tags)) {
            $metas = $xpath->query('//meta[starts-with(@name, "og:") or starts-with(@name, "article:")]');
            if ($metas) {
                foreach ($metas as $meta) {
                    if (! $meta instanceof \DOMElement) {
                        continue;
                    }
                    $name = $meta->getAttribute('name');
                    $content = $meta->getAttribute('content');
                    if ($name && $content) {
                        $tags[$name] = $content;
                    }
                }
            }
        }

        // Fallback: standard meta tags and <title>
        if (empty($tags['og:title'])) {
            $titleNode = $xpath->query('//title');
            if ($titleNode && $titleNode->length > 0) {
                $tags['og:title'] = trim($titleNode->item(0)->textContent);
            }
        }

        if (empty($tags['og:description'])) {
            $descMeta = $xpath->query('//meta[@name="description"]');
            if ($descMeta && $descMeta->length > 0 && $descMeta->item(0) instanceof \DOMElement) {
                /** @var \DOMElement $descMetaItem */
                $descMetaItem = $descMeta->item(0);
                $content = $descMetaItem->getAttribute('content');
                if ($content) {
                    $tags['og:description'] = $content;
                }
            }
        }

        return $tags;
    }

    // ── Helpers (private) ───────────────────────────────────────

    /**
     * Build a null-result array preserving source type and embed data.
     *
     * @param  array<string, string>|null  $embedData
     * @return array{title: null, description: null, image: null, site_name: null, author: null, source_type: SourceType, embed_data: ?array<string, string>, og_raw: null}
     */
    private function emptyResult(SourceType $sourceType, ?array $embedData): array
    {
        return [
            'title' => null,
            'description' => null,
            'image' => null,
            'site_name' => null,
            'author' => null,
            'source_type' => $sourceType,
            'embed_data' => $embedData,
            'og_raw' => null,
        ];
    }
}
