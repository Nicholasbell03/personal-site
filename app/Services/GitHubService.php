<?php

namespace App\Services;

use App\DataTransferObjects\ContributionActivity;
use App\DataTransferObjects\ContributionDay;
use App\DataTransferObjects\ContributionStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private const GRAPHQL_URL = 'https://api.github.com/graphql';

    private const DAYS_TO_FETCH = 90;

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
     * Transform raw weeks/days into a ContributionActivity for the last 90 days.
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
        $thirtyDaysAgo = Carbon::today()->subDays(30)->toDateString();

        $totalLast30 = 0;
        $totalLast90 = 0;

        foreach ($days as $day) {
            $totalLast90 += $day->count;

            if ($day->date >= $thirtyDaysAgo) {
                $totalLast30 += $day->count;
            }
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $streak = 0;

        $reversedDays = array_reverse($days);

        foreach ($reversedDays as $index => $day) {
            if ($day->count > 0) {
                $streak++;
                $longestStreak = max($longestStreak, $streak);

                if ($index === 0 || $currentStreak > 0) {
                    $currentStreak = $streak;
                }
            } else {
                if ($index === 0) {
                    $currentStreak = 0;
                }

                $streak = 0;
            }
        }

        $dayCount = count($days);

        return new ContributionStats(
            totalLast30Days: $totalLast30,
            totalLast90Days: $totalLast90,
            currentStreak: $currentStreak,
            longestStreak: $longestStreak,
            averagePerDay: $dayCount > 0 ? round($totalLast90 / $dayCount, 1) : 0.0,
        );
    }
}
