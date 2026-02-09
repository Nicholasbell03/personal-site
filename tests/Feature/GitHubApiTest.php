<?php

use App\DataTransferObjects\ContributionActivity;
use App\DataTransferObjects\ContributionDay;
use App\DataTransferObjects\ContributionStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeGitHubGraphQlResponse(array $contributionDays): array
{
    // Group days into weeks of 7
    $weeks = [];
    $chunk = [];

    foreach ($contributionDays as $day) {
        $chunk[] = $day;

        if (count($chunk) === 7) {
            $weeks[] = ['contributionDays' => $chunk];
            $chunk = [];
        }
    }

    if (! empty($chunk)) {
        $weeks[] = ['contributionDays' => $chunk];
    }

    $total = array_sum(array_column($contributionDays, 'contributionCount'));

    return [
        'data' => [
            'user' => [
                'contributionsCollection' => [
                    'contributionCalendar' => [
                        'totalContributions' => $total,
                        'weeks' => $weeks,
                    ],
                ],
            ],
        ],
    ];
}

function buildContributionDays(int $days, ?callable $countFn = null): array
{
    $result = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = now()->subDays($i)->toDateString();
        $count = $countFn ? $countFn($i, $days) : rand(0, 10);

        $result[] = [
            'date' => $date,
            'contributionCount' => $count,
        ];
    }

    return $result;
}

beforeEach(function () {
    Cache::flush();
    config(['services.github.username' => 'testuser']);
    config(['services.github.personal_access_token' => 'fake-token']);
});

it('returns correct JSON structure on success', function () {
    $days = buildContributionDays(30, fn () => 3);

    Http::fake([
        'api.github.com/graphql' => Http::response(fakeGitHubGraphQlResponse($days)),
    ]);

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'daily_contributions' => [
                    '*' => ['date', 'count'],
                ],
                'stats' => [
                    'total_last_7_days',
                    'total_last_30_days',
                    'current_streak',
                ],
            ],
        ]);
});

it('calculates stats correctly for known contribution data', function () {
    // Create 30 days: first 23 days have 1 contribution, last 7 days have 3
    $days = buildContributionDays(30, fn (int $daysAgo) => $daysAgo >= 7 ? 1 : 3);

    Http::fake([
        'api.github.com/graphql' => Http::response(fakeGitHubGraphQlResponse($days)),
    ]);

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk();
    $stats = $response->json('data.stats');

    // Last 7 days: subDays(7) is inclusive, so 7 days with count 3 + day-7-ago with count 1 = 22
    expect($stats['total_last_7_days'])->toBe(22);

    // Last 30 days: 23 days * 1 + 7 days * 3 = 44
    expect($stats['total_last_30_days'])->toBe(44);

    // Current streak: all 30 days have contributions > 0
    expect($stats['current_streak'])->toBe(30);
});

it('caches response so second call does not hit GitHub', function () {
    $days = buildContributionDays(30, fn () => 1);

    Http::fake([
        'api.github.com/graphql' => Http::response(fakeGitHubGraphQlResponse($days)),
    ]);

    $this->getJson('/api/v1/github/activity')->assertOk();
    $this->getJson('/api/v1/github/activity')->assertOk();

    Http::assertSentCount(1);
});

it('returns empty structure when GitHub API fails and no stale cache', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response('Internal Server Error', 500),
    ]);

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'daily_contributions' => [],
                'stats' => [
                    'total_last_7_days' => 0,
                    'total_last_30_days' => 0,
                    'current_streak' => 0,
                ],
            ],
        ]);
});

it('returns stale cache when GitHub API fails but stale data exists', function () {
    $staleActivity = new ContributionActivity(
        dailyContributions: [new ContributionDay(date: '2025-01-01', count: 5)],
        stats: new ContributionStats(
            totalLast7Days: 10,
            totalLast30Days: 42,
            currentStreak: 7,
        ),
    );

    Cache::put('api.v1.github.activity.stale', $staleActivity, 60 * 60 * 24 * 7);

    Http::fake([
        'api.github.com/graphql' => Http::response('Internal Server Error', 500),
    ]);

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'daily_contributions' => [['date' => '2025-01-01', 'count' => 5]],
                'stats' => [
                    'total_last_7_days' => 10,
                    'total_last_30_days' => 42,
                    'current_streak' => 7,
                ],
            ],
        ]);
});

it('returns empty structure when credentials are missing', function () {
    config(['services.github.username' => null]);
    config(['services.github.personal_access_token' => null]);

    Http::fake();

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'daily_contributions' => [],
                'stats' => [
                    'total_last_7_days' => 0,
                    'total_last_30_days' => 0,
                    'current_streak' => 0,
                ],
            ],
        ]);

    Http::assertNothingSent();
});

it('calculates current streak correctly when today has zero contributions', function () {
    // Days 9-3 ago have contributions, days 2-0 ago have zero
    $days = buildContributionDays(30, fn (int $daysAgo) => $daysAgo >= 3 && $daysAgo <= 9 ? 5 : 0);

    Http::fake([
        'api.github.com/graphql' => Http::response(fakeGitHubGraphQlResponse($days)),
    ]);

    $response = $this->getJson('/api/v1/github/activity');

    $response->assertOk();
    $stats = $response->json('data.stats');

    expect($stats['current_streak'])->toBe(0);
});
