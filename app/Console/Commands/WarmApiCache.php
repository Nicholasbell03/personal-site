<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Models\Blog;
use App\Models\Project;
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

        // Warm featured endpoints
        $this->line('  Blogs: featured');
        $blogController->featured();

        $this->line('  Projects: featured');
        $projectController->featured();

        // Warm index endpoints (first page)
        $this->line('  Blogs: index');
        $blogController->index(new Request);

        $this->line('  Projects: index');
        $projectController->index(new Request);

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

        $this->info('Cache warmed successfully.');

        return Command::SUCCESS;
    }
}
