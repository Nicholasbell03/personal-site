<?php

namespace App\DataTransferObjects;

use JsonSerializable;

readonly class ContributionActivity implements JsonSerializable
{
    /**
     * @param  list<ContributionDay>  $dailyContributions
     */
    public function __construct(
        public array $dailyContributions,
        public ContributionStats $stats,
    ) {}

    public static function empty(): self
    {
        return new self(
            dailyContributions: [],
            stats: ContributionStats::empty(),
        );
    }

    /**
     * @return array{daily_contributions: list<array{date: string, count: int}>, stats: array{total_last_30_days: int, total_last_90_days: int, current_streak: int, longest_streak: int, average_per_day: float}}
     */
    public function toArray(): array
    {
        return [
            'daily_contributions' => array_map(
                fn (ContributionDay $day): array => $day->toArray(),
                $this->dailyContributions,
            ),
            'stats' => $this->stats->toArray(),
        ];
    }

    /**
     * @return array{daily_contributions: list<array{date: string, count: int}>, stats: array{total_last_30_days: int, total_last_90_days: int, current_streak: int, longest_streak: int, average_per_day: float}}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
