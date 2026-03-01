<?php

namespace App\Console\Commands;

use App\Jobs\GenerateSummaryJob;
use App\Models\Share;
use Illuminate\Console\Command;

class BackfillShareSummariesCommand extends Command
{
    protected $signature = 'shares:backfill-summaries';

    protected $description = 'Dispatch jobs to generate AI summaries for shares that are missing them';

    public function handle(): int
    {
        $count = 0;

        Share::query()
            ->whereNull('summary')
            ->whereNotNull('commentary')
            ->chunkById(50, function ($shares) use (&$count) {
                foreach ($shares as $share) {
                    GenerateSummaryJob::dispatch($share);
                    $count++;
                }
            });

        $this->info("Done. {$count} job(s) dispatched for summary generation.");

        return Command::SUCCESS;
    }
}
