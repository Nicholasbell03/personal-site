<?php

namespace App\Services;

use App\Enums\SourceType;
use Illuminate\Support\Facades\Http;

class OpenGraphService
{
    /**
     * Fetch OG metadata from a URL.
     *
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string, source_type: SourceType, embed_data: ?array<string, string>, og_raw: ?array<string, string>}
     */
    public function fetch(string $url): array
    {
        $sourceType = $this->detectSourceType($url);
        $embedData = $this->extractEmbedData($url, $sourceType);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'NickBellBot/1.0 (+https://nickbell.dev)',
                ])
                ->get($url);

            if (! $response->successful()) {
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

            return [
                'title' => $ogTags['og:title'] ?? null,
                'description' => $ogTags['og:description'] ?? null,
                'image' => $ogTags['og:image'] ?? null,
                'site_name' => $ogTags['og:site_name'] ?? null,
                'source_type' => $sourceType,
                'embed_data' => $embedData,
                'og_raw' => $ogTags ?: null,
            ];
        } catch (\Throwable) {
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
     * @return array<string, string>
     */
    private function parseOgTags(string $html): array
    {
        $tags = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $metas = $xpath->query('//meta[starts-with(@property, "og:")]');

        if ($metas) {
            foreach ($metas as $meta) {
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
