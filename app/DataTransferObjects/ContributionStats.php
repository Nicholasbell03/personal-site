<?php

namespace App\DataTransferObjects;

readonly class ContributionStats
{
    public function __construct(
        public int $totalLast7Days,
        public int $totalLast30Days,
        public int $currentStreak,
    ) {}

    public static function empty(): self
    {
        return new self(
            totalLast7Days: 0,
            totalLast30Days: 0,
            currentStreak: 0,
        );
    }

    /**
     * @return array{total_last_7_days: int, total_last_30_days: int, current_streak: int}
     */
    public function toArray(): array
    {
        return [
            'total_last_7_days' => $this->totalLast7Days,
            'total_last_30_days' => $this->totalLast30Days,
            'current_streak' => $this->currentStreak,
        ];
    }
}
