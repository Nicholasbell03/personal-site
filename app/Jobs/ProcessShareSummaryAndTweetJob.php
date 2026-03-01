<?php

namespace App\Jobs;

use App\Models\Share;
use App\Services\SummaryService;
use App\Services\XPostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShareSummaryAndTweetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public Share $share,
        public bool $skipXPosting = false,
    ) {}

    public function handle(SummaryService $summaryService, XPostingService $xPostingService): void
    {
        if ($this->share->summary === null) {
            $summary = $summaryService->generate($this->share);

            if ($summary !== null) {
                $this->share->summary = $summary;
                $this->share->saveQuietly();
            }
        }

        if ($this->skipXPosting || ! $this->share->post_to_x || $this->share->summary === null || $this->share->x_post_id !== null) {
            return;
        }

        try {
            $tweetData = $xPostingService->postTweet($this->share);

            $this->share->x_post_id = $tweetData['id'];
            $this->share->saveQuietly();
        } catch (\Throwable $e) {
            Log::error('ProcessShareSummaryAndTweetJob: X posting failed', [
                'share_id' => $this->share->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessShareSummaryAndTweetJob: permanently failed', [
            'share_id' => $this->share->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
