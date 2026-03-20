<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public const CACHE_KEY = 'feed.rss';

    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): string {
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
            ]);

            $projectItems = $projects->map(fn (Project $project) => [
                'title' => $project->getDownstreamTitle(),
                'link' => $project->getDownstreamUrl(),
                'description' => $project->getDownstreamDescription(),
                'pubDate' => $project->published_at->toRfc2822String(),
                'publishedAt' => $project->published_at->getTimestamp(),
                'category' => 'Project',
                'imageUrl' => $project->getDownstreamImageUrl(),
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
}
