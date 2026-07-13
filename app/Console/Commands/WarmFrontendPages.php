<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarmFrontendPages extends Command
{
    protected $signature = 'frontend:warm-pages';

    protected $description = 'Crawl the frontend sitemap so every page is warm in the Netlify edge cache';

    private const CONCURRENCY = 8;

    public function handle(): int
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        if ($base === '' || ! str_starts_with($base, 'http')) {
            Log::error('WarmFrontendPages: app.frontend_url is not configured', [
                'value' => $base,
            ]);
            $this->error('app.frontend_url (FRONTEND_URL) is not configured.');

            return Command::FAILURE;
        }

        try {
            $sitemap = Http::timeout(20)->get("{$base}/sitemap.xml");
        } catch (\Throwable $e) {
            Log::error('WarmFrontendPages: sitemap fetch exception', [
                'exception' => $e->getMessage(),
            ]);
            $this->error("Failed to fetch sitemap: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (! $sitemap->successful()) {
            Log::error('WarmFrontendPages: sitemap fetch failed', [
                'status' => $sitemap->status(),
            ]);
            $this->error("Sitemap returned {$sitemap->status()}");

            return Command::FAILURE;
        }

        preg_match_all('#<loc>([^<]+)</loc>#', $sitemap->body(), $matches);
        $urls = array_values(array_unique($matches[1]));

        if ($urls === []) {
            Log::error('WarmFrontendPages: sitemap contained no URLs');
            $this->error('Sitemap contained no URLs.');

            return Command::FAILURE;
        }

        $failed = [];

        foreach (array_chunk($urls, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->timeout(25)->get($url),
                $chunk,
            ));

            foreach ($responses as $i => $response) {
                $url = $chunk[$i];

                if ($response instanceof \Throwable) {
                    $failed[] = $url;
                    Log::warning('WarmFrontendPages: request exception', [
                        'url' => $url,
                        'exception' => $response->getMessage(),
                    ]);
                } elseif (! $response->successful()) {
                    $failed[] = $url;
                    Log::warning('WarmFrontendPages: non-successful response', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                }
            }
        }

        $warmed = count($urls) - count($failed);
        $this->info("Warmed {$warmed}/".count($urls).' pages.');

        // A handful of failures is tolerable (transient timeouts); alert via
        // cronjob.org only when a meaningful share of the crawl failed.
        if (count($failed) > count($urls) / 4) {
            Log::error('WarmFrontendPages: too many failures', [
                'failed' => $failed,
                'total' => count($urls),
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
