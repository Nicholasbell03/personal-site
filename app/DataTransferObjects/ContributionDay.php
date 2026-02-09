<?php

namespace App\DataTransferObjects;

readonly class ContributionDay
{
    public function __construct(
        public string $date,
        public int $count,
    ) {}

    /**
     * @return array{date: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'count' => $this->count,
        ];
    }
}
