<?php

use Illuminate\Support\Facades\Artisan;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $exitCode = Artisan::call('shares:backfill-tweet-data');

        if ($exitCode !== 0) {
            throw new RuntimeException('shares:backfill-tweet-data failed with exit code '.$exitCode.': '.Artisan::output());
        }
    }
};
