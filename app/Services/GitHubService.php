<?php

namespace App\Services;

use App\DataTransferObjects\ContributionActivity;
use App\DataTransferObjects\ContributionDay;
use App\DataTransferObjects\ContributionStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const GRAPHQL_URL = 'https://api.github.com/graphql';

    private const DAYS_TO_FETCH = 30;

    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    private const STALE_CACHE_TTL = 60 * 60 * 24 * 7; // 7 days

    private const FAILURE_RETRY_TTL = 60 * 5; // 5 minutes

    private const CACHE_KEY = 'api.v1.github.activity';

    private const STALE_CACHE_KEY = 'api.v1.github.activity.stale';

    /**
     * Contribution activity, cached for 24 hours. When GitHub can't be reached, serves the last good
     * snapshot (kept for 7 days) or an empty activity, and retries GitHub after 5 minutes.
     */
    public function contributionActivity(): ContributionActivity
    {
        /** @var ContributionActivity|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $fresh = $this->fetchContributionActivity();

        if ($fresh !== null) {
            Cache::put(self::CACHE_KEY, $fresh, self::CACHE_TTL);
            Cache::put(self::STALE_CACHE_KEY, $fresh, self::STALE_CACHE_TTL);

            return $fresh;
        }

        // Live fetch failed: serve the stale snapshot but only cache it
        // briefly so the next request retries GitHub soon, instead of
        // pinning stale data under the fresh key for a full 24 hours.
        /** @var ContributionActivity $stale */
        $stale = Cache::get(self::STALE_CACHE_KEY, ContributionActivity::empty());

        Cache::put(self::CACHE_KEY, $stale, self::FAILURE_RETRY_TTL);

        return $stale;
    }

    /**
     * Fetch GitHub contribution activity for the configured user.
     */
    public function fetchContributionActivity(): ?ContributionActivity
    {
        $username = config('services.github.username');
        $token = config('services.github.personal_access_token');

        if (empty($username) || empty($token)) {
            Log::warning('GitHub contribution fetch skipped: missing credentials', [
                'has_username' => ! empty($username),
                'has_token' => ! empty($token),
            ]);

            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)
                ->withToken($token)
                ->post(self::GRAPHQL_URL, [
                    'query' => $this->buildQuery(),
                    'variables' => ['username' => $username],
                ]);

            if (! $response->successful()) {
                Log::warning('GitHub GraphQL request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $weeks = $response->json('data.user.contributionsCollection.contributionCalendar.weeks');

            if ($weeks === null) {
                Log::warning('GitHub GraphQL response missing contribution data', [
                    'response' => $response->json(),
                ]);

                return null;
            }

            return $this->transformContributions($weeks);
        } catch (\Throwable $e) {
            Log::error('GitHub contribution fetch exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function buildQuery(): string
    {
        return <<<'GRAPHQL'
        query($username: String!) {
          user(login: $username) {
            contributionsCollection {
              contributionCalendar {
                totalContributions
                weeks {
                  contributionDays {
                    contributionCount
                    date
                  }
                }
              }
            }
          }
        }
        GRAPHQL;
    }

    /**
     * Transform raw weeks/days into a ContributionActivity for the last 30 days.
     *
     * @param  list<array{contributionDays: list<array{contributionCount: int, date: string}>}>  $weeks
     */
    private function transformContributions(array $weeks): ContributionActivity
    {
        $allDays = [];

        foreach ($weeks as $week) {
            foreach ($week['contributionDays'] as $day) {
                $allDays[] = new ContributionDay(
                    date: $day['date'],
                    count: $day['contributionCount'],
                );
            }
        }

        usort($allDays, fn (ContributionDay $a, ContributionDay $b): int => $a->date <=> $b->date);

        $cutoff = Carbon::today()->subDays(self::DAYS_TO_FETCH)->toDateString();
        $today = Carbon::today()->toDateString();

        $dailyContributions = array_values(
            array_filter($allDays, fn (ContributionDay $day): bool => $day->date >= $cutoff && $day->date <= $today)
        );

        return new ContributionActivity(
            dailyContributions: $dailyContributions,
            stats: $this->calculateStats($dailyContributions),
        );
    }

    /**
     * Calculate aggregate stats from daily contribution data.
     *
     * @param  list<ContributionDay>  $days
     */
    private function calculateStats(array $days): ContributionStats
    {
        $sevenDaysAgo = Carbon::today()->subDays(7)->toDateString();

        $totalLast7 = 0;
        $totalLast30 = 0;

        foreach ($days as $day) {
            $totalLast30 += $day->count;

            if ($day->date >= $sevenDaysAgo) {
                $totalLast7 += $day->count;
            }
        }

        $currentStreak = 0;

        foreach (array_reverse($days) as $day) {
            if ($day->count > 0) {
                $currentStreak++;
            } else {
                break;
            }
        }

        return new ContributionStats(
            totalLast7Days: $totalLast7,
            totalLast30Days: $totalLast30,
            currentStreak: $currentStreak,
        );
    }
}
