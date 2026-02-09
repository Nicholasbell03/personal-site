<?php

namespace App\DataTransferObjects;

readonly class ContributionStats
{
    public function __construct(
        public int $totalLast30Days,
        public int $totalLast90Days,
        public int $currentStreak,
        public int $longestStreak,
        public float $averagePerDay,
    ) {}

    public static function empty(): self
    {
        return new self(
            totalLast30Days: 0,
            totalLast90Days: 0,
            currentStreak: 0,
            longestStreak: 0,
            averagePerDay: 0.0,
        );
    }

    /**
     * @return array{total_last_30_days: int, total_last_90_days: int, current_streak: int, longest_streak: int, average_per_day: float}
     */
    public function toArray(): array
    {
        return [
            'total_last_30_days' => $this->totalLast30Days,
            'total_last_90_days' => $this->totalLast90Days,
            'current_streak' => $this->currentStreak,
            'longest_streak' => $this->longestStreak,
            'average_per_day' => $this->averagePerDay,
        ];
    }
}
