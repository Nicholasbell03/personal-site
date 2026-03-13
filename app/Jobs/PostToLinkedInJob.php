<?php

namespace App\Jobs;

use App\Contracts\DownstreamPostable;
use App\Exceptions\LinkedInPermissionDeniedException;
use App\Exceptions\LinkedInTokenExpiredException;
use App\Models\Blog;
use App\Models\Project;
use App\Services\LinkedInPostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PostToLinkedInJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const POSTED_WITHOUT_URN = 'posted';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    private const LOCK_TTL_SECONDS = 120;

    public function __construct(
        public Blog|Project $model,
    ) {}

    public function handle(LinkedInPostingService $linkedInPostingService): void
    {
        $table = $this->model->getTable();
        $id = $this->model->id;
        $lock = Cache::lock("{$table}:{$id}:post-to-linkedin", self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            Log::info('PostToLinkedInJob: skipped because another worker is posting this content', [
                'model' => $table,
                'id' => $id,
            ]);

            return;
        }

        try {
            /** @var (Blog&DownstreamPostable)|(Project&DownstreamPostable)|null $model */
            $model = $this->model::query()->find($id);

            if ($model === null || ! $model->post_to_linkedin || $model->linkedin_post_id !== null) {
                return;
            }

            if (! config('services.linkedin.access_token') || ! config('services.linkedin.person_id')) {
                Log::warning('PostToLinkedInJob: LinkedIn credentials not configured, skipping', [
                    'model' => $table,
                    'id' => $id,
                ]);

                return;
            }

            $logContext = ['model' => $this->model::class, 'id' => $id];

            try {
                $postUrn = $linkedInPostingService->post($model, $logContext);
            } catch (LinkedInTokenExpiredException|LinkedInPermissionDeniedException $e) {
                Log::warning('PostToLinkedInJob: LinkedIn auth/permission error, will not retry', array_merge($logContext, [
                    'exception' => $e->getMessage(),
                ]));
                $this->fail($e);

                return;
            }

            $updated = $this->model::query()
                ->whereKey($id)
                ->whereNull('linkedin_post_id')
                ->update(['linkedin_post_id' => $postUrn ?: self::POSTED_WITHOUT_URN]);

            if ($updated === 0) {
                Log::warning('PostToLinkedInJob: skipped saving linkedin_post_id because content was already posted', array_merge($logContext, [
                    'linkedin_post_id' => $postUrn,
                ]));
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $table = $this->model->getTable();

        Log::error('PostToLinkedInJob: permanently failed', [
            'model' => $table,
            'id' => $this->model->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
