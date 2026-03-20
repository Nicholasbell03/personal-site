<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Project;
use App\Support\FeedCache;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __invoke(): Response
    {
        $xml = Cache::remember(FeedCache::KEY, self::CACHE_TTL, function (): string {
            $blogs = Blog::published()->latestPublished()->get();
            $projects = Project::published()->latestPublished()->get();

            $blogItems = $blogs->map(fn (Blog $blog) => [
                'title' => $blog->getDownstreamTitle(),
                'link' => $blog->getDownstreamUrl(),
                'description' => $blog->getDownstreamDescription(),
                'pubDate' => $blog->published_at->toRfc2822String(),
                'publishedAt' => $blog->published_at->getTimestamp(),
                'category' => 'Blog',
                'imageUrl' => $blog->getDownstreamImageUrl(),
                'imageType' => self::mimeTypeFromPath($blog->featured_image),
            ]);

            $projectItems = $projects->map(fn (Project $project) => [
                'title' => $project->getDownstreamTitle(),
                'link' => $project->getDownstreamUrl(),
                'description' => $project->getDownstreamDescription(),
                'pubDate' => $project->published_at->toRfc2822String(),
                'publishedAt' => $project->published_at->getTimestamp(),
                'category' => 'Project',
                'imageUrl' => $project->getDownstreamImageUrl(),
                'imageType' => self::mimeTypeFromPath($project->featured_image),
            ]);

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

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
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
