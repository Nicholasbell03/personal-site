<?php

namespace App\Console\Commands;

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    protected $signature = 'embeddings:generate
                            {--model= : Generate for a specific model (blog, project, share)}
                            {--force : Regenerate embeddings even if they already exist}';

    protected $description = 'Dispatch jobs to generate vector embeddings for content models';

    /** @var array<string, class-string> */
    private array $modelMap = [
        'blog' => Blog::class,
        'project' => Project::class,
        'share' => Share::class,
    ];

    public function handle(): int
    {
        $modelFilter = $this->option('model');
        $force = $this->option('force');

        if ($modelFilter && ! isset($this->modelMap[$modelFilter])) {
            $this->error("Invalid model: {$modelFilter}. Valid options: blog, project, share");

            return Command::FAILURE;
        }

        $models = $modelFilter
            ? [$modelFilter => $this->modelMap[$modelFilter]]
            : $this->modelMap;

        $totalDispatched = 0;

        foreach ($models as $name => $class) {
            $query = $class::query();

            if ($name === 'project') {
                $query->with('technologies');
            }

            if (! $force) {
                $query->whereNull('embedding_generated_at');
            }

            $count = 0;

            $query->chunkById(50, function ($records) use (&$count) {
                foreach ($records as $record) {
                    GenerateEmbeddingJob::dispatch($record);
                    $count++;
                }
            });

            $this->line("  {$name}: {$count} job(s) dispatched");
            $totalDispatched += $count;
        }

        $this->info("Done. {$totalDispatched} total job(s) dispatched.");

        return Command::SUCCESS;
    }
}
