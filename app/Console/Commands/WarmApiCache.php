<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Services\BlogService;
use App\Services\FeedService;
use App\Services\ProjectService;
use App\Services\ShareService;
use Illuminate\Console\Command;

class WarmApiCache extends Command
{
    protected $signature = 'api:warm-cache';

    protected $description = 'Pre-warm the API response cache for all public endpoints';

    public function handle(BlogService $blogs, ProjectService $projects, ShareService $shares, FeedService $feed): int
    {
        $this->info('Warming API cache...');

        // Warm featured endpoints
        $this->line('  Blogs: featured');
        $blogs->featured();

        $this->line('  Projects: featured');
        $projects->featured();

        $this->line('  Shares: featured');
        $shares->featured();

        // Warm index endpoints (first page)
        $this->line('  Blogs: index');
        $blogs->paginated(1);

        $this->line('  Projects: index');
        $projects->paginated(1);

        $this->line('  Shares: index');
        $shares->paginated(1);

        // Warm RSS feed
        $this->line('  RSS feed');
        $feed->rss();

        // Warm individual blog posts
        $blogSlugs = Blog::published()->pluck('slug');
        foreach ($blogSlugs as $slug) {
            $this->line("  Blog: {$slug}");
            $blogs->show($slug);
        }

        // Warm individual projects
        $projectSlugs = Project::published()->pluck('slug');
        foreach ($projectSlugs as $slug) {
            $this->line("  Project: {$slug}");
            $projects->show($slug);
        }

        // Warm individual shares
        $shareSlugs = Share::query()->pluck('slug');
        foreach ($shareSlugs as $slug) {
            $this->line("  Share: {$slug}");
            $shares->show($slug);
        }

        $this->info('Cache warmed successfully.');

        return Command::SUCCESS;
    }
}
