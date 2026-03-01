<?php

namespace App\Jobs;

use App\Models\Share;
use App\Services\SummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSummaryJob implements ShouldQueue
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
    ) {}

    public function handle(SummaryService $summaryService): void
    {
        if ($this->share->summary !== null) {
            return;
        }

        $summary = $summaryService->generate($this->share);

        if ($summary !== null) {
            $this->share->summary = $summary;
            $this->share->saveQuietly();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSummaryJob: permanently failed', [
            'share_id' => $this->share->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
