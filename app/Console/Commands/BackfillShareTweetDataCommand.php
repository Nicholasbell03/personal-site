<?php

namespace App\Console\Commands;

use App\Enums\SourceType;
use App\Models\Share;
use App\Services\OpenGraphService;
use Illuminate\Console\Command;

class BackfillShareTweetDataCommand extends Command
{
    protected $signature = 'shares:backfill-tweet-data';

    protected $description = 'Fetch and store the syndication payload for x_post shares that are missing it';

    public function handle(OpenGraphService $openGraphService): int
    {
        $stored = 0;
        $skipped = 0;

        Share::query()
            ->where('source_type', SourceType::XPost)
            ->chunkById(50, function ($shares) use ($openGraphService, &$stored, &$skipped) {
                foreach ($shares as $share) {
                    $embedData = $share->embed_data ?? [];

                    if (isset($embedData['tweet']) || ! isset($embedData['tweet_id'])) {
                        $skipped++;

                        continue;
                    }

                    $tweet = $openGraphService->fetchTweetSyndication($embedData['tweet_id']);

                    if ($tweet === null) {
                        $skipped++;

                        continue;
                    }

                    $embedData['tweet'] = $tweet;
                    $share->update(['embed_data' => $embedData]);
                    $stored++;

                    $this->line("Stored tweet payload for share {$share->id} ({$share->slug})");
                }
            });

        $this->info("Done: {$stored} stored, {$skipped} skipped (already stored, no tweet_id, or fetch failed).");

        return Command::SUCCESS;
    }
}
