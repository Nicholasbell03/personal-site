<?php

namespace App\Console\Commands;

use App\Jobs\ProcessShareSummaryAndTweetJob;
use App\Models\Share;
use Illuminate\Console\Command;

class BackfillShareSummariesCommand extends Command
{
    protected $signature = 'shares:backfill-summaries';

    protected $description = 'Dispatch jobs to generate AI summaries for shares that are missing them (no X posting)';

    public function handle(): int
    {
        $count = 0;

        Share::query()
            ->whereNull('summary')
            ->whereNotNull('commentary')
            ->chunkById(50, function ($shares) use (&$count) {
                foreach ($shares as $share) {
                    ProcessShareSummaryAndTweetJob::dispatch($share, skipXPosting: true);
                    $count++;
                }
            });

        $this->info("Done. {$count} job(s) dispatched for summary generation.");

        return Command::SUCCESS;
    }
}
