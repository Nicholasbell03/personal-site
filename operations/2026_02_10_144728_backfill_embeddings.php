<?php

use Illuminate\Support\Facades\Artisan;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    /**
     * Determine if the operation is being processed asynchronously.
     */
    protected bool $async = false;

    /**
     * Process the operation.
     */
    public function process(): void
    {
        $exitCode = Artisan::call('embeddings:generate');

        if ($exitCode !== 0) {
            throw new \RuntimeException('embeddings:generate failed with exit code '.$exitCode.': '.Artisan::output());
        }
    }
};
