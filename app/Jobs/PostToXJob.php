<?php

namespace App\Jobs;

use App\Exceptions\XCreditsDepletedException;
use App\Models\Share;
use App\Services\XPostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PostToXJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];
    private const LOCK_TTL_SECONDS = 120;

    public function __construct(
        public Share $share,
    ) {}

    public function handle(XPostingService $xPostingService): void
    {
        $lock = Cache::lock("shares:{$this->share->id}:post-to-x", self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            Log::info('PostToXJob: skipped because another worker is posting this share', [
                'share_id' => $this->share->id,
            ]);

            return;
        }

        try {
            $share = Share::query()->find($this->share->id);

            if ($share === null || ! $share->post_to_x || $share->summary === null || $share->x_post_id !== null) {
                return;
            }

            if (! config('services.x.api_key') || ! config('services.x.api_secret') || ! config('services.x.access_token') || ! config('services.x.access_token_secret')) {
                Log::warning('PostToXJob: X credentials not configured, skipping', [
                    'share_id' => $share->id,
                ]);

                return;
            }

            try {
                $tweetData = $xPostingService->postTweet($share);
            } catch (XCreditsDepletedException $e) {
                Log::warning('PostToXJob: X API credits depleted, will not retry', [
                    'share_id' => $share->id,
                ]);
                $this->fail($e);

                return;
            }

            $updated = Share::query()
                ->whereKey($share->id)
                ->whereNull('x_post_id')
                ->update([
                    'x_post_id' => $tweetData['id'],
                ]);

            if ($updated === 0) {
                Log::warning('PostToXJob: skipped saving x_post_id because share was already posted', [
                    'share_id' => $share->id,
                    'x_post_id' => $tweetData['id'],
                ]);
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PostToXJob: permanently failed', [
            'share_id' => $this->share->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
