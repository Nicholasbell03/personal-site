<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Project;
use App\Support\FeedCache;
use Illuminate\Support\Facades\Cache;

class FeedService
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    /**
     * The RSS feed XML: the latest 50 published blogs and projects, newest first. Cached until
     * any blog or project is saved (ClearsApiCache forgets FeedCache::KEY).
     */
    public function rss(): string
    {
        return Cache::remember(FeedCache::KEY, self::CACHE_TTL, function (): string {
            $blogs = Blog::published()->latestPublished()->get();
            $projects = Project::published()->latestPublished()->get();

            $blogItems = $blogs->map(fn (Blog $blog) => self::item($blog, 'Blog'));
            $projectItems = $projects->map(fn (Project $project) => self::item($project, 'Project'));

            $items = $blogItems->toBase()
                ->merge($projectItems)
                ->sortByDesc('publishedAt')
                ->take(50)
                ->values();

            return view('feed.rss', [
                'items' => $items,
                'frontendUrl' => config('app.frontend_url'),
            ])->render();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(Blog|Project $model, string $category): array
    {
        return [
            'title' => $model->getDownstreamTitle(),
            'link' => $model->getDownstreamUrl(),
            'description' => $model->getDownstreamDescription(),
            'pubDate' => $model->published_at->toRfc2822String(),
            'publishedAt' => $model->published_at->getTimestamp(),
            'category' => $category,
            'imageUrl' => $model->getDownstreamImageUrl(),
            'imageType' => self::mimeTypeFromPath($model->featured_image),
        ];
    }

    private static function mimeTypeFromPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }
}
