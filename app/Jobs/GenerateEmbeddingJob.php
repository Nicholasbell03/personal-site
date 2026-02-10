<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    /**
     * @param  Blog|Project|Share  $model
     */
    public function __construct(public Model $model) {}

    public function handle(EmbeddingService $service): void
    {
        $service->generateFor($this->model);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateEmbeddingJob: permanently failed', [
            'model' => $this->model::class,
            'id' => $this->model->getKey(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
