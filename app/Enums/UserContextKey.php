<?php

namespace App\Enums;

enum UserContextKey: string
{
    case CvSummary = 'cv_summary';
    case CvDetailed = 'cv_detailed';

    public function label(): string
    {
        return match ($this) {
            self::CvSummary => 'CV Summary',
            self::CvDetailed => 'CV Detailed',
        };
    }
}
