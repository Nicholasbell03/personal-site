<?php

namespace App\Jobs;

use App\Contracts\DownstreamPostable;
use App\Exceptions\XCreditsDepletedException;
use App\Models\Blog;
use App\Models\Project;
use App\Services\XPostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PostContentToXJob implements ShouldQueue
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
        public Blog|Project $model,
    ) {}

    public function handle(XPostingService $xPostingService): void
    {
        $table = $this->model->getTable();
        $id = $this->model->id;
        $lock = Cache::lock("{$table}:{$id}:post-to-x", self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            Log::info('PostContentToXJob: skipped because another worker is posting this content', [
                'model' => $table,
                'id' => $id,
            ]);

            return;
        }

        try {
            /** @var (Blog&DownstreamPostable)|(Project&DownstreamPostable)|null $model */
            $model = $this->model::query()->find($id);

            if ($model === null || ! $model->post_to_x || $model->x_post_id !== null) {
                return;
            }

            if (! config('services.x.api_key') || ! config('services.x.api_secret') || ! config('services.x.access_token') || ! config('services.x.access_token_secret')) {
                Log::warning('PostContentToXJob: X credentials not configured, skipping', [
                    'model' => $table,
                    'id' => $id,
                ]);

                return;
            }

            $description = $model->getDownstreamDescription();
            $url = $model->getDownstreamUrl();

            $maxLength = 280 - 23 - 2; // TCO URL length + \n\n
            $description = mb_substr($description, 0, $maxLength);
            $text = "{$description}\n\n{$url}";

            $logContext = ['model' => $this->model::class, 'id' => $id];

            try {
                $tweetData = $xPostingService->postText($text, $logContext);
            } catch (XCreditsDepletedException $e) {
                Log::warning('PostContentToXJob: X API credits depleted, will not retry', $logContext);
                $this->fail($e);

                return;
            }

            $updated = $this->model::query()
                ->whereKey($id)
                ->whereNull('x_post_id')
                ->update(['x_post_id' => $tweetData['id']]);

            if ($updated === 0) {
                Log::warning('PostContentToXJob: skipped saving x_post_id because content was already posted', array_merge($logContext, [
                    'x_post_id' => $tweetData['id'],
                ]));
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $table = $this->model->getTable();

        Log::error('PostContentToXJob: permanently failed', [
            'model' => $table,
            'id' => $this->model->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
