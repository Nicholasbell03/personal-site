<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckLinkedInTokenCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'linkedin:check-token';

    /**
     * @var string
     */
    protected $description = 'Check if the LinkedIn access token is still valid';

    public function handle(): int
    {
        $accessToken = config('services.linkedin.access_token');

        if (! $accessToken) {
            $this->warn('No LinkedIn token configured.');
            Log::warning('CheckLinkedInTokenCommand: no LinkedIn token configured');

            return self::FAILURE;
        }

        $response = Http::withToken($accessToken)
            ->get('https://api.linkedin.com/v2/userinfo');

        if ($response->successful()) {
            $this->info('LinkedIn token is valid.');
            Log::info('CheckLinkedInTokenCommand: LinkedIn token is valid');

            return self::SUCCESS;
        }

        $this->error('LinkedIn access token expired — refresh at https://www.linkedin.com/developers/');
        Log::error('CheckLinkedInTokenCommand: LinkedIn access token expired — refresh at https://www.linkedin.com/developers/', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return self::FAILURE;
    }
}
