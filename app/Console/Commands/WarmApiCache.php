<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

use function Laravel\Prompts\spin;

class WarmApiCache extends Command
{
    protected $signature = 'api:warm-cache';

    protected $description = 'Pre-warm the API response cache for all public endpoints';

    public function handle(): int
    {
        $this->info('Warming API cache...');

        $blogController = app(BlogController::class);
        $projectController = app(ProjectController::class);
        $shareController = app(ShareController::class);

        // Warm featured endpoints
        $this->line('  Blogs: featured');
        $blogController->featured();

        $this->line('  Projects: featured');
        $projectController->featured();

        $this->line('  Shares: featured');
        $shareController->featured();

        // Warm index endpoints (first page)
        $this->line('  Blogs: index');
        $blogController->index(new Request);

        $this->line('  Projects: index');
        $projectController->index(new Request);

        $this->line('  Shares: index');
        $shareController->index(new Request);

        // Warm individual blog posts
        $blogSlugs = Blog::published()->pluck('slug');
        foreach ($blogSlugs as $slug) {
            $this->line("  Blog: {$slug}");
            $blogController->show($slug);
        }

        // Warm individual projects
        $projectSlugs = Project::published()->pluck('slug');
        foreach ($projectSlugs as $slug) {
            $this->line("  Project: {$slug}");
            $projectController->show($slug);
        }

        // Warm individual shares
        $shareSlugs = Share::query()->pluck('slug');
        foreach ($shareSlugs as $slug) {
            $this->line("  Share: {$slug}");
            $shareController->show($slug);
        }

        $this->info('Cache warmed successfully.');

        return Command::SUCCESS;
    }
}
