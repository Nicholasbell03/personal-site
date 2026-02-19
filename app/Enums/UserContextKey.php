<?php

namespace App\Enums;

enum UserContextKey: string
{
    case CvSummary = 'cv_summary';
    case CvDetailed = 'cv_detailed';
    case AgentSystemPrompt = 'agent_system_prompt';

    public function label(): string
    {
        return match ($this) {
            self::CvSummary => 'CV Summary',
            self::CvDetailed => 'CV Detailed',
            self::AgentSystemPrompt => 'Agent System Prompt',
        };
    }
}
